
"""WSGI-compatible HTTP 1.1 server"""

__all__ = ["PyzoServer"]

import socket, re, datetime, logging, StringIO

_req_re = re.compile("%(method)s\s+(http://%(host)s)?%(path)s(\?%(query)s)?\s+%(version)s" % {
  "method" : "(?P<method>[A-Z]+)",
  "host" : "(?P<host>[A-Za-z0-9\-.]+(:\d+)?)",
  "path" : "(?P<path>/([A-Za-z0-9\-_.!~*'();/:@&=+$,]|\%[A-Fa-f0-9]{2})*)",
  "query" : "(?P<query>([A-Za-z0-9\-_.!~*'();/?:@&=+$,]|\%[A-Fa-f0-9]{2})*)",
  "version" : "HTTP/(?P<major>\d+)\.(?P<minor>\d+)"})
_hdr_re = re.compile("(?P<name>[^:]+):(?P<value>[^\r\n]*)\r?\n$")
_crlf_re = re.compile("\r?\n$")
_size_re = re.compile("(?P<size>[a-fA-F0-9]+)(;[^\r\n]*)?\r?\n$")

_zlog = logging.getLogger("Pyzo")

class _HTTPError(Exception) :
  pass

class PyzoServer(object) :
  def __init__(self, application) :
    self.application = application

  def __call__(self, sock, addr, server) :
    _Request(self.application, sock, addr, server)

class _Request(object) :
  envtemplate = {"wsgi.version" : (1, 0), "wsgi.multithread" : 1, "wsgi.multiprocess" : 1, "wsgi.run_once" : 0, "SCRIPT_NAME" : "", "SERVER_NAME" : socket.getfqdn()}

  def __init__(self, application, sock, addr, server) :
    try :
      # State variables for this connection
      self.sfile = _SocketFile(sock)
      self.keepalive = 1
      while self.keepalive :
        # State variables for this request
        self.environ = self.envtemplate.copy()
        self.environ["SERVER_PORT"] = str(server.server_address[1])
        self.environ["wsgi.url_scheme"] = "http" # HACK: we don't support https yet
        self.datestring = datetime.datetime.utcnow().strftime("%a, %d %b %Y %X GMT")
        self.resultlen = 0
        self.response = None
        self.response_sent = 0
        # Parse request line
        # TODO: be tolerant of extra lines
        self.sfile.fasttimeout(15)
        reqmatch = _req_re.match(self.sfile.readline())
        if not reqmatch :
          raise _HTTPError("400 Bad Request")
        _zlog.info("%-16s %s" % (addr[0], reqmatch.group("path")))
        self.environ["REQUEST_METHOD"] = reqmatch.group("method")
        self.environ["PATH_INFO"] = reqmatch.group("path")
        self.environ["QUERY_STRING"] = reqmatch.group("query")
        # Check request version
        self.reqver = (int(reqmatch.group("major")), int(reqmatch.group("minor")))
        if self.reqver[0] != 1 :
          raise _HTTPError("505 HTTP Version Not Supported")
        self.environ["SERVER_PROTOCOL"] = "HTTP/%d.%d" % self.reqver
        # Parse headers
        self.parse_headers()
        # Send "Continue"
        if "HTTP_EXPECT" in self.environ :
          if self.environ["HTTP_EXPECT"] != "100-continue" :
            raise _HTTPError("417 Expectation Failed")
          self.sfile.write("HTTP/1.1 100 Continue\r\n\r\n")
        # Require Host
        if self.reqver >= (1, 1) and not "HTTP_HOST" in self.environ :
          raise _HTTPError("400 Missing Host Header")
        if reqmatch.group("host") :
          self.environ["HTTP_HOST"] = reqmatch.group("host")
        # Read Content headers
        if "HTTP_CONTENT_LENGTH" in self.environ :
          self.environ["CONTENT_LENGTH"] = self.environ["HTTP_CONTENT_LENGTH"]
        if "HTTP_CONTENT_TYPE" in self.environ :
          self.environ["CONTENT_TYPE"] = self.environ["HTTP_CONTENT_TYPE"]
        # Decode entity data
        data = ""
        if "HTTP_TRANSFER_ENCODING" in self.environ :
          if self.environ["HTTP_TRANSFER_ENCODING"] != "chunked" :
            raise _HTTPError("501 Unimplemented Transfer-Encoding")
          while 1 :
            sizematch = _size_re.match(self.sfile.readline())
            if not sizematch :
              raise _HTTPError("400 Bad Request")
            size = int(sizematch.group("size"), 16)
            if not size :
              break
            data += self.sfile.read(size)
            if self.sfile.read(2) != "\r\n" :
              raise _HTTPError("400 Bad Request")
            self.environ["CONTENT_LENGTH"] = str(len(data))
          self.parse_headers()
        elif "HTTP_CONTENT_LENGTH" in self.environ :
          data = self.sfile.read(int(self.environ["HTTP_CONTENT_LENGTH"]))
        self.environ["wsgi.input"] = StringIO.StringIO(data)
        # Handle Connection header
        self.keepalive = (self.reqver == (1, 0) and "HTTP_CONNECTION" in self.environ and self.environ["HTTP_CONNECTION"].lower() == "keep-alive") or (self.reqver >= (1, 1) and ("HTTP_CONNECTION" not in self.environ or self.environ["HTTP_CONNECTION"].lower() != "close"))
        # Dispatch to application
        result = application(self.environ, self.start_response)
        # Try to find a len
        try :
          self.resultlen = len(result)
        except TypeError :
          pass
        # Write response data
        try :
          for data in result :
            if data :
              self.write(data)
          if not self.response_sent :
            self.write("")
        finally :
          if hasattr(result, "close") :
            result.close()
    except socket.error : # The socket died, so just give up
      pass
    except _HTTPError, e : # Fatal HTTP protocol error
      try :
        # Log, then send the error response
        _zlog.error(str(e))
        self.sfile.write("HTTP/1.1 %s\r\nDate: %s\r\nContent-Length: 0\r\n\r\n" % (str(e), self.datestring))
      except :
        pass
    except : # Something bad happened
      try :
        # Log, then send a courtesy 500 if we haven't already started writing
        _zlog.critical("*** UNHANDLED EXCEPTION ***\n", exc_info = True)
        if not self.response_sent :
          self.sfile.write("HTTP/1.1 500 Internal Server Error\r\nDate: %s\r\nContent-Length: 0\r\n\r\n" % self.datestring)
      except :
        pass

  def start_response(self, status, response_headers, exc_info = None) :
    try :
      if exc_info and self.response_sent :
        raise exc_info[0], exc_info[1], exc_info[2]
      assert not self.response or not self.response_sent and exc_info
      self.response = (status, response_headers)
      return self.write
    finally :
      exc_info = None

  def write(self, data) :
    assert self.response
    if not self.response_sent :
      # Add a Date header if the application didn't give one
      if not [1 for k, v in self.response[1] if k.lower() == "date"] :
        self.response[1].append(("Date", self.datestring))
      # Try to guess a Content-Length if one wasn't given
      if not [1 for k, v in self.response[1] if k.lower() == "content-length"] :
        if self.resultlen == 1 :
          self.response[1].append(("Content-Length", str(len(data))))
        else :
          self.keepalive = 0
      # Add a Connection header
      if self.keepalive and self.reqver == (1, 0) :
        self.response[1].append(("Connection", "Keep-Alive"))
      elif not self.keepalive and self.reqver >= (1, 1) :
        self.response[1].append(("Connection", "close"))
      # Send the response status and headers
      self.sfile.write("HTTP/1.1 %s\r\n%s\r\n" % (self.response[0], "".join(["%s: %s\r\n" % (k, v) for k, v in self.response[1]])))
      self.response_sent = 1
    self.sfile.write(data)

  def parse_headers(self) :
    while 1 :
      hdrline = self.sfile.readline()
      if _crlf_re.match(hdrline) :
        break
      hdrmatch = _hdr_re.match(hdrline)
      if not hdrmatch :
        raise _HTTPError("400 Bad Request") # TODO: support multiline headers
      ename = "HTTP_" + hdrmatch.group("name").upper().replace("-", "_")
      if ename in self.environ :
        self.environ[ename] += "," + hdrmatch.group("value").strip()
      else :
        self.environ[ename] = hdrmatch.group("value").strip()

class _SocketFile(object) :
  def __init__(self, sock, timeout = 300) :
    self.sock = sock
    self.timeout = timeout
    self.curtimeout = timeout
    self.sock.settimeout(timeout)
    self.buffer = ""

  def read(self, size) :
    if len(self.buffer) >= size :
      result = self.buffer[:size]
      self.buffer = self.buffer[size:]
      return result
    else :
      while 1 :
        data = self.sock.recv(size - len(self.buffer))
        if not data :
          raise socket.error, "disconnected"
        if self.curtimeout != self.timeout :
          self.curtimeout = self.timeout
          self.sock.settimeout(self.curtimeout)
        if len(self.buffer) + len(data) == size :
          result = self.buffer + data
          self.buffer = ""
          return result
        else :
          self.buffer += data

  def readline(self) :
    try :
      i = self.buffer.index("\n") + 1
    except ValueError :
      while 1 :
        data = self.sock.recv(1024)
        if not data :
          raise socket.error, "disconnected"
        if self.curtimeout != self.timeout :
          self.curtimeout = self.timeout
          self.sock.settimeout(self.curtimeout)
        try :
          i = data.index("\n") + 1
        except :
          self.buffer += data
        else :
          line = self.buffer + data[:i]
          self.buffer = data[i:]
          return line
    else :
      line = self.buffer[:i]
      self.buffer = self.buffer[i:]
      return line

  def write(self, data) :
    self.sock.sendall(data)

  def fasttimeout(self, ft) :
    if not self.buffer :
      self.curtimeout = ft
      self.sock.settimeout(ft)


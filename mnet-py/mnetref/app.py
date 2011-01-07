
import PyzoServer
import SocketServer
import mnet
import shelve
import xmlrpclib
import logging
logging.basicConfig()

# where to run the test server
address = ("localhost", 10051)

class MyApp(mnet.Application) :
  wwwroot = shelve.open("app.db")["wwwroot"]
  serverpath = "/"

  def getpeer(self, wwwroot) :
    peers = shelve.open("peers.db")
    try :
      peer = peers[wwwroot]
    except KeyError :
      return None
    return mnet.Peer(self, wwwroot = peer["wwwroot"], serverpath = peer["serverpath"], cert = peer["cert"])

  def savepeer(self, peer) :
    peers = shelve.open("peers.db")
    peers[peer.wwwroot] = {"cert" : peer.cert, "wwwroot" : peer.wwwroot, "serverpath" : peer.serverpath}

  def saveapp(self) :
    app = shelve.open("app.db")
    app["cert"] = self.cert
    app["key"] = self.key
    app["key_history"] = self.key_history

def handle(environ, start_response) :
  msg = environ["wsgi.input"].read()
  params, method = xmlrpclib.loads(msg)
  start_response("200 OK", [])
  print "in handle() with", method
  return (xmlrpclib.dumps(("hi",), methodresponse = 1),)

def makeapp() :
  app = shelve.open("app.db")
  return MyApp(handle, key = app["key"], cert = app["cert"], key_history = app["key_history"])

if __name__ == "__main__" :
  SocketServer.TCPServer.allow_reuse_address = 1
  server = SocketServer.TCPServer(address, PyzoServer.PyzoServer(makeapp()))
  server.serve_forever()

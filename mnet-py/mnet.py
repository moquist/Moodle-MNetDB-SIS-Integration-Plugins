
# MNet - XMLRPC encryption and security library
# Copyright (C) 2009 Shuttleworth Foundation and Escondido Charter High School
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# Author: Brett Heath-Wlaz
#
# This library is designed to iteroperate with the PHP version of MNet derived
# from Moodle. That code can be found here:
# $ git clone http://git.catalyst.net.nz/mnet.git/
#

import M2Crypto
import xml.dom.minidom
import xmlrpclib
import urllib2
import base64
import sha
import time
import StringIO

class MNetError(Exception) :
  def __init__(self, app, code, text, key = None) :
    self.app = app
    self.code = code
    self.text = text
    self.key = key

  def fault(self) :
    return OutMessage(self.app, """<?xml version="1.0" encoding="utf-8"?>
      <methodResponse>
        <fault>
          <value>
            <struct>
              <member>
                <name>faultCode</name>
                <value><int>%d</int></value>
              </member>
              <member>
                <name>faultString</name>
                <value><string>%s</string></value>
              </member>
            </struct>
          </value>
        </fault>
      </methodResponse>""" % (self.code, self.text))

  def __str__(self) :
    return self.text

class SignatureError(MNetError) :
  pass

class EncryptionError(MNetError) :
  pass

class InMessage :
  def __init__(self, app, content) :
    self.app = app
    self.content = content
    try :
      self.dom = xml.dom.minidom.parseString(content)
    except :
      raise MNetError(self.app, 712, "invalid xml")

  def gettag(self, path, default=None) :
    cursor = self.dom
    for name in path :
      try :
        cursor = cursor.getElementsByTagName(name)[0]
      except IndexError :
        if default :
          return default
        else :
          raise MNetError(self.app, 712, "missing expected xml tag %s" % path)
    return str(cursor.firstChild.data)

  def decrypt(self, privkey) :
    """Try to decrypt the message with the given key. Returns the decrypted
    message. Raises EncryptionError if it fails"""
    data = base64.b64decode(self.gettag(["EncryptedData", "CipherValue"]))
    key = base64.b64decode(self.gettag(["EncryptedKey", "CipherValue"]))
    rsa = M2Crypto.RSA.load_key_string(privkey, lambda x : None)
    try :
      rc4key = rsa.private_decrypt(key, M2Crypto.RSA.pkcs1_padding)
    except M2Crypto.RSA.RSAError :
      raise EncryptionError(self.app, 7023, "encryption invalid")
    content = M2Crypto.RC4.RC4(rc4key).update(data)
    return InMessage(self.app, content)

  def unsign(self, pubkey) :
    """Verify the signature with the given key. Returns the payload if
    successful. Raises SignatureError if it fails"""
    content = base64.b64decode(self.gettag(["object"]))
    signature = base64.b64decode(self.gettag(["SignatureValue"]))
    rsa = M2Crypto.X509.load_cert_string(pubkey).get_pubkey().get_rsa()
    try :
      if not rsa.verify(sha.new(content).digest(), signature) :
        raise SignatureError(self.app, 710, "signature not recognized")
    except M2Crypto.RSA.RSAError :
      raise SignatureError(self.app, 710, "signature not recognized")
    return InMessage(self.app, content)

class OutMessage :
  def __init__(self, app, content) :
    self.app = app
    self.content = content

  def sign(self, privkey) :
    """Sign the message with the given key. Returns a new message."""
    dig = sha.new(self.content).digest()
    digest = base64.b64encode(dig)
    rsa = M2Crypto.RSA.load_key_string(privkey, lambda x : None)
    signature = base64.b64encode(rsa.sign(dig))
    retrievalmethod = self.app.wwwroot
    obj = base64.b64encode(self.content)
    wwwroot = self.app.wwwroot
    timestamp = int(time.time())
    serverpath = self.app.serverpath
    return OutMessage(self.app, """<?xml version="1.0" encoding="utf-8"?>
      <signedMessage>
        <Signature Id="MNetSignature" xmlns="http://www.w3.org/2000/09/xmldsig#">
          <SignedInfo>
            <CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
            <SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#dsa-sha1"/>
            <Reference URI="#XMLRPC-MSG">
              <DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
              <DigestValue>%s</DigestValue>
            </Reference>
          </SignedInfo>
          <SignatureValue>%s</SignatureValue>
          <KeyInfo>
            <RetrievalMethod URI="%s"/>
          </KeyInfo>
        </Signature>
        <object ID="XMLRPC-MSG">%s</object>
        <wwwroot>%s</wwwroot>
        <timestamp>%s</timestamp>
        <mnet-protocol>2.0</mnet-protocol>
        <server-path>%s</server-path>
      </signedMessage>""" % (digest, signature, retrievalmethod, obj, wwwroot, timestamp, serverpath))

  def encrypt(self, pubkey) :
    """Encrypt the message with the given key. Returns a new message"""
    # *** WORKAROUND NOTE ***
    # These next lines are a workaround for a bug in M2Crypto.
    # https://bugzilla.osafoundation.org/show_bug.cgi?id=11686
    # Once it is fixed, the code below should be replaced with the following:
    # rsa = M2Crypto.X509.load_cert_string(pubkey).get_pubkey().get_rsa()
    x509 = M2Crypto.X509.load_cert_string(pubkey)
    pkey = x509.get_pubkey()
    rsa_ptr = M2Crypto.m2.pkey_get1_rsa(pkey.pkey)
    rsa = M2Crypto.RSA.RSA_pub(rsa_ptr)
    # *** END WORKAROUND ***
    rc4key = M2Crypto.Rand.rand_bytes(16)
    data = base64.b64encode(M2Crypto.RC4.RC4(rc4key).update(self.content))
    key = base64.b64encode(rsa.public_encrypt(rc4key, M2Crypto.RSA.pkcs1_padding))
    wwwroot = self.app.wwwroot
    return OutMessage(self.app, """<?xml version="1.0" encoding="utf-8"?>
      <encryptedMessage>
        <EncryptedData Id="ED" xmlns="http://www.w3.org/2001/04/xmlenc#">
          <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#arcfour"/>
          <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
            <ds:RetrievalMethod URI="#EK" Type="http://www.w3.org/2001/04/xmlenc#EncryptedKey"/>
            <ds:KeyName>XMLENC</ds:KeyName>
          </ds:KeyInfo>
          <CipherData>
            <CipherValue>%s</CipherValue>
          </CipherData>
        </EncryptedData>
        <EncryptedKey Id="EK" xmlns="http://www.w3.org/2001/04/xmlenc#">
          <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-1_5"/>
          <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
            <ds:KeyName>SSLKEY</ds:KeyName>
          </ds:KeyInfo>
          <CipherData>
            <CipherValue>%s</CipherValue>
          </CipherData>
          <ReferenceList>
            <DataReference URI="#ED"/>
          </ReferenceList>
          <CarriedKeyName>XMLENC</CarriedKeyName>
        </EncryptedKey>
        <wwwroot>%s</wwwroot>
      </encryptedMessage>""" % (data, key, wwwroot))

class Application :
  wwwroot = "http://localhost"
  serverpath = "/"

  keep_keys = 10

  def __init__(self, childapp, cert, key, key_history) :
    self.childapp = childapp
    self.cert = cert
    self.key = key
    self.key_history = key_history
    if not self.key or not self.cert :
      self.gen_key()
    else :
      self.check_key()

  def getpeer(self, wwwroot) :
    raise NotImplementedError("you must override and implement Application.getpeer")

  def savepeer(self, peer) :
    raise NotImplementedError("you must override and implement Application.savepeer")

  def saveapp(self) :
    raise NotImplementedError("you must override and implement Application.saveapp")

  def __call__(self, environ, start_response) :
    peer = None # we'll fill this in when we identify the sender
    try :
      msg = InMessage(self, environ["wsgi.input"].read())

      encrypted, signed = 0, 0

      # If the message is encrypted and/or signed, there should be a wwwroot
      # element telling us who it is.
      if msg.dom.documentElement.tagName == "encryptedMessage" or msg.dom.documentElement.tagName == "signedMessage" :
        peer = self.getpeer(msg.gettag(["wwwroot"]))
        if not peer :
          raise MNetError(self, 7020, "unknown wwwroot")

      # Decrypt the message. If our current key fails to work, it might be
      # because the peer has an outdated certificate of ours, so check our
      # recent history for a working key. If we find one, inform the client of
      # our new key with a "7025" fault.
      if msg.dom.documentElement.tagName == "encryptedMessage" :
        encrypted = 1
        try :
          msg = msg.decrypt(self.key)
        except EncryptionError :
          for key in self.key_history :
            try :
              msg.decrypt(key)
            except EncryptionError :
              continue # keep trying
            else :
              raise MNetError(self, 7025, self.cert, key) # sign with the matching old key
          raise

      # Validate the signature. If it doesn't work, we might have an outdated
      # certificate for the peer, so try pinging them once to see if they
      # respond with a "7025" key update. Also update the protocol version and
      # server path if they've changed.
      if msg.dom.documentElement.tagName == "signedMessage" :
        signed = 1
        newprotocol = msg.gettag(["mnet-protocol"], 1)
        newserverpath = msg.gettag(["server-path"], '/')
        try :
          msg = msg.unsign(peer.cert)
        except SignatureError :
          peer.ping()
          msg = msg.unsign(peer.cert)
        if peer.protocol != newprotocol or peer.serverpath != newserverpath :
          peer.protocol, peer.serverpath = newprotocol, newserverpath
          self.savepeer(peer)

      # The result must be XMLRPC, so decode it.
      try :
        params, method = xmlrpclib.loads(msg.content)
      except :
        raise MNetError(self, 712, "invalid xmlrpc")

      # The only unsigned message we ever accept is a keyswap call. We'll only
      # accept unencrypted messages from trusted peers.
      if not signed :
        if method != "system/keyswap" :
          raise MNetError(self, 711, "message not signed")
      else :
        if not encrypted and not peer.trusted :
          raise MNetError(self, 7021, "forbidden transport")

      # Intercept the following functions and handle them here.
      methodmap = {
        "auth/mnet/auth.php/user_authorize" : "user_authorize",
        "mnet/singlesignon/user_authorize" : "user_authorize",
        "auth/mnet/auth.php/kill_child" : "kill_child",
        "mnet/singlesignon/kill_child" : "kill_child",
        "auth/mnet/auth.php/keepalive_server" : "keepalive_server",
        "mnet/singlesignon/keepalive_server" : "keepalive_server",
        "system/ping" : "ping",
        "system.ping" : "ping",
        "system/keyswap" : "keyswap",
        "system.keyswap" : "keyswap"}

      if method in methodmap :
        start_response("200 OK", [("Content-type", "text/xml; charset=utf-8")])
        res = OutMessage(self, xmlrpclib.dumps((getattr(self, methodmap[method])(*params),), methodresponse = 1))
        if peer :
          res = res.sign(self.key).encrypt(peer.cert)
        return (res.content,)

      state = {"res_ok" : 0, "buffer" : ""}

      def start_mnet(status, response_headers, exc_info = None) :
        def write_mnet(s) :
          state["buffer"] += s
        write = start_response(status, response_headers, exc_info)
        if status.startswith("200") :
          state["res_ok"] = 1
          return write_mnet
        else :
          state["res_ok"] = 0
          return write

      environ["wsgi.input"] = StringIO.StringIO(msg.content)
      secondpart = "".join(self.childapp(environ, start_mnet))
      res = OutMessage(self, state["buffer"] + secondpart)
      if state["res_ok"] and peer :
        res = res.sign(self.key).encrypt(peer.cert)
      return (res.content,)

    except MNetError, e :
      # Return a fault message, signed and encrypted if we know who we're talking to.
      start_response("200 OK", [("Content-type", "text/xml; charset=utf-8")])
      err = e.fault()
      if peer :
        err = err.sign(e.key or self.key).encrypt(peer.cert)
      return (err.content,)

  def check_key(self) :
    """Check if our current key doesn't exist, or is expired, and generate one
    if so"""
    if not self.cert :
      self.gen_key()
    x509 = M2Crypto.X509.load_cert_string(self.cert)
    t = x509.get_not_after()
    if time.time() > time.mktime(time.strptime(str(t), "%b %d %H:%M:%S %Y %Z")) :
      self.gen_key()

  def gen_key(self) :
    """Generate a new key and rotate the previous one through the history"""
    rsa = M2Crypto.RSA.gen_key(2048, 65537)
    pkey = M2Crypto.EVP.PKey()
    pkey.assign_rsa(rsa)
    cert = M2Crypto.X509.X509()
    cert.set_pubkey(pkey)
    now = int(time.time())
    timestart = M2Crypto.ASN1.ASN1_UTCTIME()
    timestart.set_time(now)
    timeend = M2Crypto.ASN1.ASN1_UTCTIME()
    timeend.set_time(now + 28 * 24 * 60 * 60) # default 28 day cert
    cert.set_not_before(timestart)
    cert.set_not_after(timeend)
    name = M2Crypto.X509.X509_Name()
    name.add_entry_by_txt('CN', 0x1001, self.wwwroot, -1, -1, 0)
    cert.set_subject_name(name)
    cert.sign(pkey, "sha1")
    if self.key :
      self.key_history.insert(0, self.key) 
    del self.key_history[self.keep_keys:]
    self.key = rsa.as_pem(cipher = None)
    self.cert = cert.as_pem()
    self.saveapp()

  def user_authorize(self, token, ua) :
    raise MNetError(self, 713, "no such function")

  def kill_child(self, username, ua) :
    raise MNetError(self, 713, "no such function")

  def keepalive_server(self, usernames) :
    raise MNetError(self, 713, "no such function")

  def ping(self) :
    return "pong"

  # Mahara doesn't pass a serverpath, so default.
  def keyswap(self, wwwroot, cert, serverpath='/') :
    return self.cert

class Peer :
  def __init__(self, app, wwwroot, serverpath = '/', cert = '', trusted = 0, protocol = "2.0") :
    self.app = app
    self.wwwroot = wwwroot
    self.serverpath = serverpath
    self.cert = cert
    self.trusted = trusted
    self.protocol = protocol

  def call(self, method, params) :
    # Encode the XMLRPC, sign and encrypt, POST it to the peer.
    msg = OutMessage(self.app, xmlrpclib.dumps(params, methodname = method))
    if method != "system/keyswap" :
      msg = msg.sign(self.app.key).encrypt(self.cert)
    try :
      res = InMessage(self.app, urllib2.urlopen(self.wwwroot + self.serverpath, msg.content).read())
    except urllib2.URLError :
      raise MNetError(self, 1, "POST failed")

    encrypted, signed = 0, 0

    # Decrypt the response. We must be willing to accept a message encrypted
    # with an outdated key, because it's possible that both sides have outdated
    # certificates. In that case the response will be a "7025" encrypted with
    # an old key, and if we rejected it we'd be at a mutual impasse.
    if res.dom.documentElement.tagName == "encryptedMessage" :
      encrypted = 1
      try :
        res = res.decrypt(self.app.key)
      except EncryptionError :
        decrypted = 0
        for key in self.app.key_history :
          try :
            res = res.decrypt(key)
            decrypted = 1
            break
          except EncryptionError :
            continue
        if not decrypted :
          raise

    # Validate the signature and update the protocol and server path.
    if res.dom.documentElement.tagName == "signedMessage" :
      signed = 1
      newprotocol = res.gettag(["mnet-protocol"], 1)
      newserverpath = res.gettag(["server-path"], '/')
      res = res.unsign(self.cert)
      if self.protocol != newprotocol or self.serverpath != newserverpath :
        self.protocol, self.serverpath = newprotocol, newserverpath
        self.app.savepeer(self)

    # Decode the XMLRPC result. If we get a "7025", store it and retry the
    # call.
    try :
      value = xmlrpclib.loads(res.content)[0][0]
    except xmlrpclib.Fault, e :
      if e.faultCode == 7025 :
        self.cert = e.faultString
        self.app.savepeer(self)
        return self.call(method, params)
      raise MNetError(self.app, e.faultCode, e.faultString)

    if method != 'system/keyswap' :
      if not signed :
        raise MNetError(self.app, 711, "message not signed")
      if not encrypted :
        raise MNetError(self, 7021, "forbidden transport")
    return value

  def ping(self) :
    """Send an encrypted ping to the remote system. Returns true if the ping
    succeeded, false otherwise"""
    try :
      if self.protocol == "1.0" :
        return type(self.call("system/listServices", ())) == type([])
      else :
        return self.call("system/ping", ()) == "pong"
    except MNetError :
      return 0

  def user_authorize(self, token, ua) :
    if self.protocol == "1.0" :
      return self.call("auth/mnet/auth.php/user_authorize", (token, ua))
    else :
      return self.call("mnet/singlesignon/user_authorize", (token, ua))

  def kill_child(self, username, ua) :
    if self.protocol == "1.0" :
      return self.call("auth/mnet/auth.php/kill_child", (username, ua))
    else :
      return self.call("mnet/singlesignon/kill_child", (username, ua))

  def keepalive_server(self, usernames) :
    if self.protocol == "1.0" :
      return self.call("auth/mnet/auth.php/keepalive_server", (usernames,))
    else :
      return self.call("mnet/singlesignon/keepalive_server", (usernames,))


mnet-wsgi implementation notes

#############################################
# Creating the Application and Peer objects
#############################################

Your code is responsible for creating mnet.Application and mnet.Peer objects
which represent the local and remote systems, respectively. In the case of
mnet.Application, you will need to instantiate a subclass (see next section).
Here are the signatures of the constructors of these classes and a description
of their arguments.

Application.__init__(self, childapp, cert, key, key_history)
  childapp : a callable WSGI application object that will handle the XMLRPC
  cert : the local public key certificate in PEM format
  key : the local RSA private key in unencrypted PEM format
  key_history : a list of former RSA private keys in unencrpyted PEM format

Peer.__init__(self, app, wwwroot, serverpath, cert, trusted = 0, protocol = "2.0")
  app : the Application instance
  wwwroot : the unique wwwroot of this peer
  serverpath : the rest of the path following the wwwroot
  cert : the peer's public key certificate in PEM format
  trusted : if true, we will accept unencrypted messages (not recommended)
  protocol : the version of the MNet protocol to use

######################################
# Subclassing the Application object
######################################

The MNet library does not directly handle the storage and retrieval of
crypto certificates and peer records. You will need to create a subclass of
mnet.Application and override the following members:

Application.saveapp(self) : This method will be called whenever the
application's key changes. Your implementation should fetch the "key", "cert",
and "key_history" member variables from the object and save them in
permanent storage.

Application.getpeer(self, wwwroot) : This method is called to retrieve a Peer
object based on its unique wwwroot. Your implementation should look up the
peer record from storage, and return a mnet.Peer object representing the
peer. If the wwwroot isn't recognized, return None.

Application.savepeer(self, peer) : This method will be called whenever a
peer's information changes. Your implementation should fetch the "serverpath",
"cert", and "protocol" member variables from the Peer object and save them to
permanent storage.

Application.wwwroot : This class variable is the unique name of the local
server. You should set it to the root address of your MNet server.

Application.serverpath : This class variable is the rest of the path after the
wwwroot. You should set it so that when combined with wwwroot, it gives the
complete URL of the local MNet service.

Application.keep_keys = 10 : (optional) This class variable specifies the
number of old keys to keep in the history. It must be at least 1 for key
updating to work.

############################
# Bootstrapping the system
############################

When you instantiate an mnet.Application subclass for the first time, you can
pass None for the "key" and "cert" arguments, and it will immediately generate
a new RSA key and save it.

Before you can communicate with a peer, you must have a record of that peer's
certificate. This is typically handled through a separate administrative
function. The most straightforward way to do this is to instantiate an
mnet.Peer object with the new peer's information, and then manually call
Application.savepeer.

#########################################
# Receiving and initiating XMLRPC calls
#########################################

mnet.Application is designed to act as WSGI middleware. You can pass an
instance to any WSGI server. When a message arrives, the supplied "childapp"
WSGI callable will be invoked with a plain HTTP request containing an XMLRPC
message. It should handle the request however it sees fit, and return a plain
XMLRPC response.

To initiate an XMLRPC call, you should first retrieve a Peer object by calling
Application.getpeer. Then invoke the following function on it:

Peer.call(self, method, params)
  method : the string name of the method to call on the remote side
  params : a tuple of positional arguments to the function

The return value of "call" is the result of the remote function. If anything
goes wrong during the call, it will raise mnet.MNetError.

#########################################
# Certificate expiration and key update
#########################################

mnet.Application has a function called "check_key" that should be called
periodically to check for certificate expiration. If the certificate is
expired, it will generate a new one and save it. The system will continue to
work even if certificates are not refreshed, but it's a good idea to make sure
this happens in a relatively timely manner.

To force a key refresh, call "gen_key"

###################
# Object lifetime
###################

Because both mnet.Application and mnet.Peer objects may change periodically as
a result of certificate updates, it's best to make sure not to keep multiple
long-lasting copies of these objects around, since the changes would not
propagate to all of them. Either create fresh copies for every message and
then discard them, or cache and reuse a single authoritative copy everywhere.

#################
# Single signon
#################

mnet.Application also provides some hooks for implementing single signon. If
you wish to use it, your subclass should also override the following methods:

Application.user_authorize(self, token, ua) : This method is called by a
remote system to authenticate a user via a one-time token that was provided
through the browser. Your implementation should check the token, and if it
matches, return a dict of information about the user. The dict should contain
at the very least a "username" field with the name of the user to sign on.

Application.kill_child(self, username, ua) : This method is called by a remote
system to terminate a user's session. Your implementation should delete the
given session.

Application.keepalive_server(self, usernames) : This method is called by a
remote system to refresh one or more sessions. Your implementation should
reset any idle timers on the sessions given by the provided list of usernames.


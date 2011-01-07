#!/usr/bin/python
#
# Copyright (C) 2010 Brett Heath-Wlaz
# Copyright (C) 2010 Majen.net Consulting
# Copyright (C) 2010 SAU16, Exeter, NH
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

import config
import mnet
import shelve, sys

class ShelveApp(mnet.Application) :
  def __init__(self, childapp) :
    s = shelve.open(config.FILE_APP)
    ShelveApp.wwwroot = s["wwwroot"]
    ShelveApp.serverpath = "/"
    mnet.Application.__init__(self, childapp, s["cert"], s["key"], s["key_history"])

  def getpeer(self, wwwroot) :
    try :
      return mnet.Peer(self, **shelve.open(config.FILE_PEERS)[wwwroot])
    except KeyError :
      return None

  def savepeer(self, peer) :
    shelve.open(config.FILE_PEERS)[peer.wwwroot] = {"cert" : peer.cert, "wwwroot" : peer.wwwroot, "serverpath" : peer.serverpath}

  def saveapp(self) :
    shelve.open(config.FILE_APP).update({"cert" : self.cert, "key" : self.key, "key_history" : self.key_history})
    open(config.FILE_CERT, "w").write(self.cert)

def usage() :
  print """ShelveApp.py is a small program that initializes server.py or client.py, and adds/removes MNet peers (such as Moodle, Mahara, or another instance of server.py or client.py).
usage:
ShelveApp.py init <wwwroot>
ShelveApp.py addpeer <wwwroot> <serverpath> <certfile> # if you have a local copy of the remote certificate
ShelveApp.py addpeer <wwwroot> {moodle | mahara | mnetpy} # automatically fetch remote certificate
ShelveApp.py delpeer <wwwroot>
ShelveApp.py info"""
  sys.exit()

if __name__ == "__main__" :
  if len(sys.argv) == 1 :
    usage()
  if sys.argv[1] == "init" :
    shelve.open(config.FILE_APP).update({"wwwroot" : sys.argv[2], "cert" : None, "key" : None, "key_history" : []})
    ShelveApp(None)
  elif sys.argv[1] == "addpeer" :
    if len(sys.argv) < 5 :
      # Get the key from the remote server.
      pathmap = {
        "moodle" : "/mnet/xmlrpc/server.php",
        "mahara" : "/api/xmlrpc/server.php",
        "mnetpy" : "/",
        }
      app = ShelveApp(None)
      peer = mnet.Peer(app, sys.argv[2], pathmap[sys.argv[3]])
      cert = peer.call("system/keyswap", (app.wwwroot, app.cert))
      shelve.open(config.FILE_PEERS)[sys.argv[2]] = {"wwwroot" : sys.argv[2], "serverpath" : pathmap[sys.argv[3]], "cert" : cert}
    elif len(sys.argv) == 5 :
      # Get the key from a local file.
      shelve.open(config.FILE_PEERS)[sys.argv[2]] = {"wwwroot" : sys.argv[2], "serverpath" : sys.argv[3], "cert" : file(sys.argv[4]).read()}
  elif sys.argv[1] == "delpeer" :
    del shelve.open(config.FILE_PEERS)[sys.argv[2]]
  elif sys.argv[1] == "info" :
    app = shelve.open(config.FILE_APP)
    print "app wwwroot: %s" % app["wwwroot"]
    print "app cert:"
    print app["cert"]
    print "app old keys: %d" % len(app["key_history"])
    print
    for peer in shelve.open(config.FILE_PEERS).values() :
      print "peer wwwroot: %s" % peer["wwwroot"]
      print "peer serverpath: %s" % peer["serverpath"]
      print "peer cert:"
      print peer["cert"]
      print
  else :
    usage()


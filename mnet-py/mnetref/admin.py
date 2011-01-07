
import shelve
import sys

# test server admin tool usage:

# python admin.py initapp <wwwroot>
# python admin.py listapp
# python admin.py addpeer <wwwroot> <serverpath> <certfile>
# python admin.py delpeer <wwwroot>
# python admin.py listpeers

op = sys.argv[1]
app = shelve.open("app.db")
peers = shelve.open("peers.db")

if op == "initapp" :
  wwwroot = sys.argv[2]
  app["wwwroot"] = wwwroot
  app["cert"] = None
  app["key"] = None
  app["key_history"] = []
elif op == "listapp" :
  print "wwwroot:", app["wwwroot"]
  print app["cert"]
  print "number of old keys:", len(app["key_history"])
elif op == "addpeer" :
  wwwroot, serverpath, certfile = sys.argv[2:]
  peers[wwwroot] = {"wwwroot" : wwwroot, "serverpath" : serverpath, "cert" : file(certfile).read()}
elif op == "delpeer" :
  wwwroot = sys.argv[2]
  del peers[wwwroot]
elif op == "listpeers" :
  for peer in peers.values() :
    print "wwwroot:", peer["wwwroot"]
    print "serverpath:", peer["serverpath"]
    print peer["cert"]
    print
else :
  print "unknown op"

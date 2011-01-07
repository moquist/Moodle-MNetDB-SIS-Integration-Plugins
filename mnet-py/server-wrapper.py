# This is a stupid hack to get around specific limitations on Windows Server
# 2005 when the MNet server.py needs to be automatically started *after* boot.
#
# You probably do not want to use this unless you know that you do...
#
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
# Author: Matt Oquist <moquist@majen.net>
#
# This is a lame-brained wrapper to work around some even lamer-brained SISes
# that run in Windows. Here's the deal:
# 1) the SIS and DB must be started /manually/, and the mnet server cannot be started before the DB is running -- so we can't start MNet during boot.
# 2) we can't trouble the sysadmin to manually start the mnet server when she also starts the SIS
# :. We need a way to ensure MNet starts after the DB
#
# This wrapper can be run by server-startup.vbs as a scheduled task that will not allow multiple MNet servers to be started. Plus, this can also be used instead of figuring out how to get MNet server to run as a Windows service... It's a workaround on a hack on a workaround, but it'll be good enough for now.

import config
import csv
import os
import sys
import re

def getlock() :
  p = open(config.PIDFILE, 'a')
  p.write(str(os.getpid()) + os.linesep)
  p.close()
  bmoc = whodat()
  if bmoc == os.getpid() :
    return True
  return False

def trylock() :
  if getlock() :
    return True
  bmoc = whodat()
  bmocinfo = os.popen('tasklist /fo csv /nh /v /fi "pid eq %d"' % bmoc).read()
  if len(bmocinfo) and re.search('python', bmocinfo, re.IGNORECASE) :
    return False
  os.unlink(config.PIDFILE)
  return getlock()

def whodat() :
  p = open(config.PIDFILE, 'r')
  bmoc = p.readline().rstrip(os.linesep)
  p.close()
  return int(bmoc)

if __name__ == "__main__" :
  if os.name != 'nt' :
    print "This is only necessary for Windows."
    sys.exit(1)
  if trylock() :
    os.system(config.WINCMD)
  else :
    print "no lock for me"

  




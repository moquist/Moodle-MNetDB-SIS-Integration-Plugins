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
import ShelveApp
import datetime, sys
import time
import readline
import base64

app = ShelveApp.ShelveApp(None)
peer = app.getpeer(sys.argv[1])

while 1 :
  try :
    sql = raw_input('> ')
    ask = time.time()
    rows = peer.call("sql_execute", (sql,))
    receive = time.time()
    count = 0
    data = []
    for row in rows :
      #print tuple(base64.b64decode(fval) if type(fval) == str else fval for fval in row)
      for fval in row :
        if type(fval) == str :
          data.append(base64.b64decode(fval))
        else :
          data.append(fval)
      print data
      count += 1
    print "%d results fetched in %f seconds" % (count, (receive - ask))
  except EOFError :
    print
    sys.exit(0)
  except KeyboardInterrupt :
    print
  except Exception, inst :
    print type(inst)
    print inst.args
    print inst


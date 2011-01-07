'' You probably don't want to use this, unless you know that you do. This is
'' a hack to work around a specific set of limitations in a Windows Server 2005
'' environment.

'' This can be run in a scheduled task to ensure that MNet service is started on
'' a running Windows server.

'' The server-wrapper path below needs to be customized for each system, and this will only work if .py files are associated with Python.
Set WshShell = CreateObject("WScript.Shell") 
WshShell.Run chr(34) & "c:\mnetdb\server-wrapper.py" & Chr(34), 0
Set WshShell = Nothing

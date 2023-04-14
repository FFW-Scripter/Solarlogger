:start
wget http://192.168.x.x/api/livedata/status -O data.json
wget --post-file=data.json [importURL] -O result.txt
timeout /t 5 /nobreak
goto start

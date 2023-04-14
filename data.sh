#!/bin/bash
while true; do
  wget http://192.168.x.x/api/livedata/status -O data.json
  wget --post-file=data.json [importURL] -O result.txt
  sleep 5
done

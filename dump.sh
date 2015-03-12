#!/bin/bash

## Binary path ##
#MONGO="/usr/bin/mongo"
#MONGODUMP="/usr/bin/mongodump"

BAK="./dump"
echo $BAK

/usr/bin/mongodump -o $BAK/`date +"%Y_%m_%d__"`

#/usr/bin/mongodump -d plumcake -c proxies -o $BAK/proxies`date +"%Y_%m_%d__"`
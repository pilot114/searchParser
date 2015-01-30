#!/bin/bash

## Binary path ##
#MONGO="/usr/bin/mongo"
#MONGODUMP="/usr/bin/mongodump"

BAK="./dump"
echo $BAK

/usr/bin/mongodump --out $BAK/`date +"%Y_%m_%d__"`
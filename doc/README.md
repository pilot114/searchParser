### Установка

213.239.221.172
6XYvdHYkaDH4At
php upuniq.php 2015-03-23:12 2015-04-08:12

UA_NA131361
I4mk70oITA

nload -a 3600 -t 100

kill all cron php tasks:
kill $(ps aux | grep '[p]hp' | awk '{print $2}')

check in console
curl -LI URL

Для работы требуется cron, php с curl, mongo и mongoClient.

TODO



0.5:
* backlink parser
* перед delivery чекать доступность самих адресатов, чтобы
  не портить прокси.

Следующая итерация (0.6):
* разобраться с интерфесом для воркеров
* Шаблоны подгружаемые через ajax -> админка приложение
* выборка статистики по времени, график по ссылкам и уникам, круг.диаг run/pause/stop
* по стате -> проверить надежность текущего функционала
* workflow скрипты -> полная автоматизация

### cron

* * * * * php /var/www/searchparser/run/backlink_worker.php
* * * * * php /var/www/searchparser/run/worker.php
* * * * * php /var/www/searchparser/run/ref_delivery.php
* 1 * * * /var/www/searchparser/dump.sh
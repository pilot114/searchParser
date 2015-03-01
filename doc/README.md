### Установка

6XYvdHYkaDH4At
php upuniq.php 2015-02-25:12 2015-02-28:12

check in console
curl -LI URL

Для работы требуется cron, php с curl, mongo и mongoClient.

TODO



0.5:
* backlink parser
* перед delivery чекать доступность самих адресатов, чтобы
  не портить прокси.

Следующая итерация (0.6):
* Шаблоны подгружаемые через ajax -> админка приложение
* выборка статистики по времени, график по ссылкам и уникам, круг.диаг run/pause/stop
* по стате -> проверить надежность текущего функционала
* workflow скрипты -> полная автоматизация

### cron

* * * * * php /var/www/searchparser/run/backlink_worker.php
* * * * * php /var/www/searchparser/run/worker.php
*/5 * * * * php /var/www/searchparser/run/ref_delivery.php
* 1 * * * /var/www/searchparser/dump.sh
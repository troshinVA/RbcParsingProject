Как запустить локально:

1. Склонировать проект 
2. Собрать контейнеры sudo docker-compose up -d --build
3. Запустить sudo docker-compose up
4. Зайти в контейнер sudo docker-compose exec php /bin/bash
5. Внутри запустить composer install
6. Запустить миграции symfony console doctrine:migration:migrate

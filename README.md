Как запустить локально:

1. Склонировать проект 
2. Собрать контейнеры sudo docker-compose up -d --build
3. Запустить sudo docker-compose up
4. Зайти в контейнер sudo docker-compose exec php /bin/bash
5. Внутри запустить composer install
6. Запустить миграции symfony console doctrine:migration:migrate

Протестировать методы можно запустив локально фронт предварительно слив его с https://github.com/troshinVA/RbcParsingVueFront

Либо протестировать api методы по получению списка и обновлению методов через Postman:
1. POST localhost:8088/articles
{
    "itemsOnPage": 3,
    "lastId": 0 
}
itemsOnPage - возвращаемое кол-во новостей начиная с айди lastId

2. PATCH localhost:8088/article/update_rating/
Body Example: {
    "id": 1524,
    "rating": 10
}
id - id новости в БД 
rating - новое значение рейтинга

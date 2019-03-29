# Сфера.API

## Получение данных

### Фильтрация

filter[city_id]=5

| Operator | Description           | Example
|----------|-----------------------|-------------
| ct       | String contains       | ct:Peter
| sw       | String starts with    | sw:admin
| ew       | String ends with      | ew:gmail.com
| eq       | Equals                | eq:3
| ne       | Not equals            | ne:4
| gt       | Greater than          | gt:2
| ge       | Greater than or equal | ge:3
| lt       | Lesser than           | lt:4
| le       | Lesser than or equal  | le:3
| in       | In array              | in:1|2|3
| nl       | Is NULL               | nl:
| nn       | Not NULL              | nn:
| bw       | Between               | bw:1|100
| nb       | Not between           | nb:1|100

### Сортировка
sort=-id

### Постраничная разбивка

page[number]=0&page[size]=10

### Связанные данные

    ?include=author,comments.author

Отключить связи по умолчанию
    ?include=

Добавляет к связям по умолчнию другие связи

    ?include=*,author

### Scopes

    ?scope=active
    ?scope=forType:1
    ?scope=
    ?scope=*,active

### Выборка только определенных полей
fields[user]=id,name


## Создание

## Обновление

## Удаление

## Ошибки

~~~json
{
    "success": false,
    "error": {
        "code"
    },
    "errors": [
        {
            "links": {
                "about": "{link that leads to further details about this problem}"
            },
            "status": "{HTTP status code}",
            "code": "{application-specific error code}",
            "title": "{summary of the problem}",
            "detail": "{explanation specific to this occurrence of the problem}",
            "source": {
                "pointer": "{a JSON Pointer to the associated entity in the request document}",
                "parameter": "{a string indicating which URI query parameter caused the error}"
            },
            "meta": {}
        }
    ],
}
~~~

# Модуль СЕО везде
____
## Установка 
### 1. Загрузите в папку на сайте  /bitrix/modules/
![Alt-img1](https://images.itb-bx.ru/git/seoeverywhere/1_upload.png)
### 2.Заходим в админку 
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/2_modules_path.png)
### 3. Нажимаем кнопку установить
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/3_module_in_list.png)
### 4. Вывод на старнице
a. Вставить данный код на странице категории где будет вывод тегов.
```php
<?$APPLICATION->IncludeComponent("seoeverywhere:tags", "", Array(), false);?>
```
b. Обернуть текст на страницах категории в этот тег
```html
<!-- PATTERN --> Замена <!-- END_PATTERN -->
```
____
## Настройки модуля
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/4_module_settings.png)
### 1. Список метатегов
 ![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/5_module_settings.png)
Тут всё понятно.
Только всегда обращаем внимание, что если есть поддомены и тд то добавляем URL без адреса сайта к примеру - /katalog/gitary/
### 2. Список ссылок
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/6_module_settings.png)
Старый адрес
Новый ЧПУ адрес
Смотрим за / в конце
Так же смотрим что бы если заполнено в 1 пункте то поменять адрес ссылки на новую
Смотрим на то что если есть поддомены то без адреса сайта
### 3. Список тегов
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/7_module_setting.png)
Добавляем для поддоменов без адреса сайта
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/8_view_tags_site.png)
### 4. Настройки модуля
В качестве инфоблока указать инфоблок с товарами, например:
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/9_module_settings.png)
В строку с массивом фильтра прописываем значение, указанное в скобках:
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/10_module_settins.png)
Выбираем индексный файл каталога (index.php). Если у нас url вида 
/catalog/letnyaya_spetsodezhda/**** - выбираем файл:
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/11_public_file.png)
Нажимаем «Применить». После этого нужно подправить значения в ЧПУ (добавить в начале «/», если он отсутствует): 
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/12_module_settins.png)
Применяем, и переходим к заполненю генерации заголовков и мета-тегов: 
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/13_module_settings.png)
Под блоком с полями для заполнения отразились все доступные фильтры. Для вставки переменной, нужно установить курсор в нужное место, и кликнуть на нужное свойство, например:
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/14_module_settings.png)
Если нужно, чтобы после значения фильтра был пробел – пишем так:
```
{153.FILTER_TYPE| }
```
Если нужно прописать значение с маленькой буквы:
```
{152.FILTER_COLOR|!-L}
```
Также доступно склонение по падежам. Примеры использования указаны чуть ниже списка свойств:
![Alt-img2](https://images.itb-bx.ru/git/seoeverywhere/15_module_settings.png)
<?php

namespace APITools\Exceptions;

class LogicException extends \Exception
{
    protected $codes = [
        100 => 'Неизвестная ошибка',
        101 => 'Ошибка авторизации',
        102 => 'POST-параметры должны передаваться в формате JSON',
        103 => 'Требуется авторизация',
        104 => 'Запрашиваемый метод API не найден',
        105 => 'Email не подтвержден',
        106 => 'Неверный код подтверждения',
        107 => 'Учетная запись уже активирована',
        108 => 'Учетная запись не обнаружена',
        109 => 'Учетная запись не активирована',

        201 =>  'Ошибка заполнения полей',
        209 =>  'Отсутствует обязательное поле',
        210 =>  'Поле не может быть пустым',
        211 =>  'Неверный формат числового поля',
        212 =>  'Неверный формат булевого поля',
        214 =>  'Неверный формат поля с плавающей точкой',

        215 =>  'Неверный формат поля email',
        216 =>  'Неверный формат поля телефон',
        217 =>  'Неверный формат поля email или телефон',
        219 =>  'Уже зарегистрирован',

        220 =>  'Неверное значение поля',
        221 =>  'Неверный формат даты',

        301 =>  'Запись не нейдена',


        500 => 'Service Unavailable',
        501 => 'Не определены модули',
        502 => 'Не определены поля модуля',
        503 => 'Не определена таблица с данными',
        504 => 'Не определено поле таблицы',
        505 => 'Не определена реакция action',
        506 => 'Пустой запрос where к БД',
        507 => 'Модуль не определен',
        508 => 'Ошибка при добавлении данных',
        509 => 'Ошибка доступа к данным',
        510 => 'Поле недопустимо для поиска',
        511 => 'Ошибка при удалении данных',
        512 => 'Ошибка параметров',
        513 => 'Ошибка проверки параметров по action',
        514 => 'Ошибка при сохранении данных',
        515 => 'Ошибка при обновлении данных',
        516 => 'Не определено поле key',
        517 => 'Нет вариантов enum',
        518 => 'Отсутствует ключевое поле',
        519 => 'Не определно поле where',
        520 => 'Отсутствует поле',
    ];

    protected $subcode = null;
    protected $subtext = null;
    protected $errors = null;

    public function __construct($message = null, $code = 0, Exception $previous = null){

        if( is_array($message) ) {
            $this->subcode=$message[0];
            $this->subtext=$message[1];
            $message=$this->subtext;
        }else{
            if( $code !== 500) {
                $code = 500;
            }
        }

        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode() {
        return $this->subcode;
    }

    public function getErrorText($code = null) {

        if( $code === null ) {
            if( $this->subtext ) {
                return $this->subtext;
            }

            $code = $this->getMessage();
        }

        if( isset($this->codes[$code]) ) {
            return $this->codes[$code];
        }

        return $code;
    }

    public function setErrors(array $errors) {
        //print_r($errors);
        foreach ($errors as $value) {
            $value['error_text'] = $this->getErrorText( $value['error_code']);
            $this->errors[] = $value;
        }
    }

    public function getErrors() {
        return $this->errors;
    }

}
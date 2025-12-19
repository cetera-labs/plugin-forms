<?php

namespace Forms;

class Form extends \Cetera\DbObject
{

    public static $CAPTCHA_TYPE_SIMPLE = 1;
    public static $CAPTCHA_TYPE_STRONG = 2;
    public static $CAPTCHA_TYPE_RECAPTCHA = 3;
    public static $formID = '';
    public static $clearFormData = false;
    public static $reCaptchaPublic = '';
    public static $formFields = [];

    public static function getTable()
    {
        return 'forms';
    }

    public static function getById($id)
    {
        try {
            return parent::getById($id);
        } catch (\Exception $e) {
            throw new \Exception('Форма ID=' . $id . ' не найдена');
        }
    }

    /**
     * Получение списка форм
     *
     * @return array
     */
    public static function getList()
    {
        foreach (\Forms\Form::enum() as $f) {
            $data[] = $f->fields;
        }
        return $data;
    }

    /**
     * Разбор шаблона вывода формы
     *
     * @param $template - текст шаблона
     * @param $formID идентификатор формы
     * @param $resultMsg сообщение о результате обработки данных
     * @return string
     */
    public static function parseTemplate(
        $template,
        $formID,
        $useTwig,
        $reCaptchaPublic,
        $errorMsg,
        $resultMsg,
        $twigParams
    ) {
        self::$formID = $formID;
        self::$reCaptchaPublic = $reCaptchaPublic;

        if ($useTwig) {
            $template = \Cetera\Application::getInstance()->getTwig()->createTemplate($template)->render($twigParams);
        }

        $template = preg_replace_callback(
            '/\[([^\]]*)(\*?) ([^\]]*)\]/isU',
            'self::parseTagsRecursive',
            $template
        );

        $template = str_replace(['[formerror]', '[formresult]'], [$errorMsg, $resultMsg], $template);

        return $template;
    }

    public static function getFieldList($template, $useTwig, $twigParams)
    {
        if ($useTwig) {
            $template = \Cetera\Application::getInstance()->getTwig()->createTemplate($template)->render($twigParams);
        }

        preg_replace_callback(
            '/\[([^\]]*)(\*?) ([^\]]*)\]/isU',
            'self::parseTagsRecursive',
            $template
        );

        return self::$formFields;
    }

    /**
     * Разбор тега поля формы
     *
     * @param $matches
     * @return string
     */
    private static function parseTagsRecursive($matches)
    {
        $fieldName = self::getFieldName($matches[3]);

        if (!self::$clearFormData) {
            $fieldValue = !empty($_REQUEST[$fieldName]) ? $_REQUEST[$fieldName] : '';
        } else {
            $fieldValue = '';
        }

        if (!empty($fieldName) && !empty($fieldValue)) {
            self::$formFields[$fieldName] = $fieldValue;
        }

        $fieldRequired = !empty($matches[2]) ? ' required ' : '';
        $fieldProps = self::getFieldProps($matches[3]);
        if ($fieldProps != '') {
            $fieldStaticValue = self::getFieldStaticValue($fieldProps);
        }

        switch ($matches[1]) {
            case 'text':
                if ($fieldValue != '') {
                    $fieldValue = ' value="' . $_REQUEST[$fieldName] . '"';
                }
                return '<input type="text" name="' . $fieldName . '"' . $fieldValue . $fieldProps . $fieldRequired . '/>';

            case 'tel':
                if ($fieldValue != '') {
                    $fieldValue = ' value="' . $_REQUEST[$fieldName] . '"';
                }
                return '<input type="tel" name="' . $fieldName . '"' . $fieldValue . $fieldProps . $fieldRequired . '/>';

            case 'date':
                if ($fieldValue != '') {
                    $fieldValue = ' value="' . $_REQUEST[$fieldName] . '"';
                }
                return '<input type="date" name="' . $fieldName . '"' . $fieldValue . $fieldProps . $fieldRequired . '/>';


            case 'email':
                if ($fieldValue != '') {
                    $fieldValue = ' value="' . $_REQUEST[$fieldName] . '"';
                }
                return '<input type="email" name="' . $fieldName . '"' . $fieldValue . $fieldProps . $fieldRequired . '/>';

            case 'textarea':
                return '<textarea name="' . $fieldName . '"' . $fieldProps . $fieldRequired . '>' . $fieldValue . '</textarea>';

            case 'submit':
                $fieldValue = '';
                $fieldTpl = $matches[3];

                $propsDelimiterPos = strpos($matches[3], '|');
                if ($propsDelimiterPos !== false) {
                    $fieldTpl = substr($matches[3], 0, $propsDelimiterPos);
                }

                preg_match('/["\'](.*)["\']/isU', $fieldTpl, $arMatches);
                if ($arMatches[1] != '') {
                    $fieldValue = $arMatches[1];
                }

                if (strpos($matches[3], '|') !== false || strpos($fieldTpl, 'class') === false) {
                    return '<input type="submit" name="send" value="' . $fieldValue . '"' . $fieldProps . '/>';
                } else {
                    return '<input type="submit" name="send" value=' . $matches[3] . '/>';
                } // Совместимость с предыдущими версиями

            case 'submitbtn':
                $propsDelimiterPos = strpos($matches[3], '|');
                if ($propsDelimiterPos !== false) {
                    $fieldValue = substr($matches[3], 0, $propsDelimiterPos);
                } else {
                    $fieldValue = $matches[3];
                }

                $fieldValue = str_replace('\'', '', $fieldValue);

                return '<button type="submit"' . $fieldProps . '>' . $fieldValue . '</button>';

            case 'file':
                return '<input type="file" name="' . $fieldName . '"' . $fieldProps . $fieldRequired . '/>';

            case 'radio':
                return '<input type="radio" name="' . $fieldName . '"' . ($fieldValue == $fieldStaticValue ? 'checked' : '') . $fieldProps . $fieldRequired . '/>';

            case 'checkbox':
                return '<input type="checkbox" name="' . $fieldName . '[]"' . ($fieldValue ? 'checked' : '') . $fieldProps . $fieldRequired . '/>';

            case 'select':
                $propsDelimiterPos = strpos($matches[3], '|');
                if ($propsDelimiterPos !== false) {
                    $sOptionsValues = substr($matches[3], 0, $propsDelimiterPos);
                } else {
                    $sOptionsValues = $matches[3];
                }

                $optionsStr = '';
                preg_match_all('/\"(.*)\"/isU', $sOptionsValues, $arOptions, PREG_SET_ORDER);

                foreach ($arOptions as $option) {
                    if (isset($option[1]) && $option[1] != '') {
                        if ($fieldValue != '' && $option[1] == $fieldValue) {
                            $selectedStr = ' selected';
                        } else {
                            $selectedStr = '';
                        }

                        $optionsStr .= '<option value="' . $option[1] . '"' . $selectedStr . '>' . $option[1] . '</option>';
                    }
                }
                return '<select name="' . $fieldName . '"' . $fieldProps . $fieldRequired . '>' . $optionsStr . '</select>';

            case 'captchaimg':
            case 'captchastrongimg':
                return '<img alt="captcha" title="captcha" src="/plugins/forms/scripts/captcha.php?f=' . self::$formID . '"' . $fieldProps . '/>';

            case 'captchainput':
                return '<input type="text" name="captcha_code"' . $fieldProps . $fieldRequired . '/>';

            case 'recaptcha':
                if (self::$reCaptchaPublic != '' && self::$formID != '') {
                    // Отложенная загрузка ReCaptcha по первому взаимодействию пользователя с браузером
                    return '
                        <script>
                            function initReCaptcha() {
                                var script = document.createElement(\'script\');
                                script.src = \'https://www.google.com/recaptcha/api.js\';
                                script.async = true;
                                script.onload = function () {
                                  grecaptcha.ready(function () {
                                      var mysitekey = "' . self::$reCaptchaPublic . '";
                                      grecaptcha.render("recaptcha' . self::$formID . '", {
                                        "sitekey" : mysitekey
                                      });
                                  }) 
                                }
                                document.body.appendChild(script);
                            }
                        
                            function loadReCaptcha(){
                                //удаляем EventListeners
                                if (window.detachEvent) { //поддержка IE8
                                    window.detachEvent("onscroll", loadReCaptcha);
                                    window.detachEvent("onmousemove", loadReCaptcha);
                                    window.detachEvent("ontouchmove", loadReCaptcha);
                                    window.detachEvent("onresize", loadReCaptcha);
                                } else {
                                    window.removeEventListener("scroll", loadReCaptcha, false);
                                    window.removeEventListener("mousemove", loadReCaptcha, false);
                                    window.removeEventListener("touchmove", loadReCaptcha, false);
                                    window.removeEventListener("resize", loadReCaptcha, false);
                                }
                                //запускаем функцию загрузки ReCaptcha
                                if(document.readyState == "complete") {
                                    initReCaptcha();
                                } else {
                                    if(window.attachEvent) {
                                        window.attachEvent("onload", initReCaptcha);
                                    } else {
                                        window.addEventListener("load", initReCaptcha, false);
                                    }
                                }
                                //Устанавливаем куку по которой отличаем первый и второй хит
                                var cookie_date = new Date ();
                                cookie_date.setTime (cookie_date.getTime() + 60*60*28*1000); //24 часа для Москвы
                                document.cookie = "RecaptchaLoaded=1;path=/;expires=" + cookie_date.toGMTString();
                            }
                            
                            if (document.cookie.search ( \'RecaptchaLoaded\' ) < 0) { //проверяем, первый ли это визит на наш сайт
                                if (window.attachEvent) { // поддержка IE8
                                    window.attachEvent("onscroll", loadReCaptcha);
                                    window.attachEvent("onmousemove", loadReCaptcha);
                                    window.attachEvent("ontouchmove", loadReCaptcha);
                                    window.attachEvent("onresize", loadReCaptcha);
                                } else {
                                    window.addEventListener("scroll", loadReCaptcha, {capture: false, passive: true});
                                    window.addEventListener("mousemove", loadReCaptcha, {capture: false, passive: true});
                                    window.addEventListener("touchmove", loadReCaptcha, {capture: false, passive: true});
                                    window.addEventListener("resize", loadReCaptcha, {capture: false, passive: true});
                                }
                            } else {
                                loadReCaptcha();
                            }
                        </script>
                        <div id="recaptcha' . self::$formID . '"></div>
                    ';
                } else {
                    return '<!-- Необходимо установить Recaptcha public key в настройках формы -->';
                }

            case 'formresult':
                return '[formresult]';

            case 'formerror':
                return '[formerror]';
        }
    }

    /**
     * Проверка введенных данных
     *
     * @throws \Exception
     */
    public static function validateData($form)
    {
        $resultStr = '';

        $captchaFieldName = 'captcha_code_' . $form->id;

        $useCaptcha = self::useCaptcha($form->template);
        if ($useCaptcha !== false) // Проверка CAPTCHA
        {
            $captchaErrorText = $form->msgCaptchaError != '' ? $form->msgCaptchaError : 'Неверный код';

            switch ($useCaptcha) {
                case self::$CAPTCHA_TYPE_SIMPLE:

                    $savedInSessionValue = \Cetera\Application::getInstance()->getSession()->{$captchaFieldName};

                    if ($savedInSessionValue == '' || trim(strtolower($_REQUEST['captcha_code'])) != $savedInSessionValue) {
                        $resultStr = $captchaErrorText;
                    }
                    break;

                case self::$CAPTCHA_TYPE_STRONG:

                    $savedInSessionValue = \Cetera\Application::getInstance()->getSession()->{$captchaFieldName};

                    if ($savedInSessionValue != '' && isset($_REQUEST['captcha_code']) && $_REQUEST['captcha_code'] != '') {
                        $sessionCode = substr($savedInSessionValue, 1, strlen($savedInSessionValue) - 2);

                        if (trim(strtolower($_REQUEST['captcha_code'])) != $sessionCode) {
                            $resultStr = $captchaErrorText;
                        }
                    } else {
                        $resultStr = $captchaErrorText;
                    }
                    break;

                case self::$CAPTCHA_TYPE_RECAPTCHA:
                    $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . $form->reCaptchaPrivate . '&response=' . (array_key_exists('g-recaptcha-response',
                            $_POST) ? $_POST["g-recaptcha-response"] : '') . '&remoteip=' . $_SERVER['REMOTE_ADDR'];
                    $resp = json_decode(file_get_contents($url), true);

                    if ($resp['success'] == false) {
                        $resultStr = $captchaErrorText;
                    }
                    break;
            }

            unset($_SESSION[$captchaFieldName]);

            if ($resultStr != '') {
                return $resultStr;
            }
        }

        preg_match_all('/\[(.*)\]/isU', $form->template, $arFields, PREG_SET_ORDER);

        foreach ($arFields as $field) {
            preg_match_all('/\[(.*)(\*?) (.*)\]/isU', $field[0], $arFieldInfo, PREG_SET_ORDER);

            $fieldType = $arFieldInfo[0][1];
            $fieldName = self::getFieldName($arFieldInfo[0][3]);

            if ($arFieldInfo[0][2] != '') {

                if ($fieldType == 'file') {
                    if (!isset($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['name'] == '') {
                        $resultStr = $form->msgRequired;
                        break;
                    }
                } elseif (empty($_REQUEST[$fieldName])) {
                    $resultStr = $form->msgRequired;
                    break;
                }
            }


        }

        return $resultStr;
    }

    /**
     * Получение имени поля для составных результатов регулярного выражения
     * @param $fieldNameStr
     * @return string
     */
    private static function getFieldName($fieldNameStr)
    {
        $spacePosition = strpos($fieldNameStr, ' ');
        if ($spacePosition !== false) {
            return substr($fieldNameStr, 0, $spacePosition);
        } else {
            return $fieldNameStr;
        }
    }

    private static function getFieldProps($fieldStr)
    {
        $propsDelimiterPos = strpos($fieldStr, '|');
        if ($propsDelimiterPos !== false) {
            return ' ' . trim(substr($fieldStr, $propsDelimiterPos + 1));
        } else {
            return '';
        }
    }

    private static function getFieldStaticValue($value)
    {
        preg_match("/value=\"(.*)\"/isU", $value, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        } else {
            return '';
        }
    }

    /**
     * Обработка и отправка введенных данных формы
     *
     * @throws \Exception
     * @throws \phpmailerException
     */
    public static function performAction($form, $twigParams)
    {
        if ($_SERVER['DOCUMENT_ROOT'] != '/var/www/tpl.fastsite.ru/www' || self::useCaptcha($form->template)) {
            $form->formFields = self::getFieldList($form->template, $form->useTwig, $twigParams);

            // Первое письмо
            $data['mailTo'] = preg_split("/[\s;,]+/", self::replaceTemplateInValue($form->mailTo));
            $data['mailFrom'] = self::replaceTemplateInValue($form->mailFrom);
            $data['mailBody'] = self::replaceTemplateInValue($form->mailBody);
            $data['mailSubject'] = self::replaceTemplateInValue($form->mailSubject);
            preg_match('/(.*) <(.*@.*)>/isU', $data['mailFrom'], $data['mailFromInfo']);

            $attachments = self::getInputFileList($form->template);

            $staticAttachments = self::getTmplAttachments($form->mailTmplAttachments);

            $result = new \Forms\Result(["text" => $data['mailBody']]);
            $result->saveResult($form->id);

            // Второе письмо
            if ($form->mail2) {
                $data2['mailTo'] = preg_split("/[\s;,]+/", self::replaceTemplateInValue($form->mail2To));
                $data2['mailFrom'] = self::replaceTemplateInValue($form->mail2From);
                $data2['mailBody'] = self::replaceTemplateInValue($form->mail2Body);
                $data2['mailSubject'] = self::replaceTemplateInValue($form->mail2Subject);
                preg_match('/(.*) <(.*@.*)>/isU', $data2['mailFrom'], $data2['mailFromInfo']);
            }

            try {
                if (!empty($data['mailTo']) && $data['mailTo'] != '') { // отправка 1-ого письма
                    if ($form->mailAttachments) {
                        self::sendMail($data, $form->mailHtml, array_merge($attachments, $staticAttachments));
                    } else {
                        self::sendMail($data, $form->mailHtml, $staticAttachments);
                    }
                }
                if ($form->mail2 && !empty($data2['mailTo']) && $data2['mailTo'] != '') { // отправка 2-ого письма
                    if ($form->mail2Attachments) {
                        self::sendMail($data2, $form->mail2Html, $attachments);
                    } else {
                        self::sendMail($data2, $form->mail2Html);
                    }
                }

                if (is_callable('\Cetera\Event::trigger')) {
                    \Cetera\Event::trigger('FORMS_SUCCESS', [
                            'form' => $form
                        ]
                    );
                }

                self::$clearFormData = true;

                return $form->msgSuccess;

            } catch (\Exception $e) {
                return $form->msgFail;
            }
        }
    }

    /**
     * Список файлов из шаблона
     *
     * @param $template
     * @return array
     */
    public static function getInputFileList($template)
    {

        $arFiles = [];
        $arFileNames = [];

        preg_match_all('/\[file.*]/isU', $template, $arFields, PREG_SET_ORDER);

        foreach ($arFields as $field) {
            preg_match_all('/\[(.*)(\*?) (.*)\]/isU', $field[0], $arFieldInfo, PREG_SET_ORDER);

            $arFileNames[] = self::getFieldName($arFieldInfo[0][3]);
        }

        foreach ($arFileNames as $fileName) {
            if ($_FILES[$fileName]['tmp_name'] != '') {
                $arFiles[] = ['PATH' => $_FILES[$fileName]['tmp_name'], 'NAME' => $_FILES[$fileName]['name']];
            }
        }

        return $arFiles;
    }

    public static function getTmplAttachments($sFiles)
    {
        $arFiles = [];

        $arPaths = explode(' ', $sFiles);

        foreach ($arPaths as $path) {
            $path = trim($path);

            if ($path != '' && file_exists(DOCROOT . $path)) {
                $arFiles[] = ['PATH' => DOCROOT . $path];
            }
        }

        return $arFiles;
    }

    /**
     * Отправка письма
     *
     * @param $data массив параметров
     * @throws \Exception
     * @throws \phpmailerException
     */
    public static function sendMail($data, $mailHtml, $attachments = [])
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        if ($mailHtml) {
            $mail->ContentType = 'text/html';
        } else {
            $mail->ContentType = 'text/plain';
        }

        if (!empty($data['mailFromInfo'][1]) && !empty($data['mailFromInfo'][2])) {
            $mail->setFrom($data['mailFromInfo'][2], $data['mailFromInfo'][1]);
        } else {
            $mail->setFrom($data['mailFrom']);
        }

        $mail->CharSet = 'utf-8';
        $mail->Subject = $data['mailSubject'];
        $mail->Body = $data['mailBody'];

        foreach ($data['mailTo'] as $mailTo) {
            $mail->AddAddress(strtolower($mailTo));
        }

        // Отправка вложений
        foreach ($attachments as $key => $value) {
            $mail->AddAttachment($value['PATH'], $value['NAME']);
        }

        $mail->Send();
        //if ($log) fwrite($log, $data['mailTo'] . " - OK\n"); куда-нибудь логгировать надо, наверное
    }

    /**
     * Подстановка переменных в шаблоне
     *
     * @param $value
     * @return mixed
     */
    public static function replaceTemplateInValue($value)
    {
        return preg_replace_callback(
            '/\[(.*)\]/isU',
            'self::parseTagsTemplateInValue',
            $value
        );
    }

    /**
     * Callback-функция обработки переменных в шаблоне
     *
     * @param $matches
     * @return mixed|string
     */
    public static function parseTagsTemplateInValue($matches)
    {
        $app = \Cetera\Application::getInstance();

        // Поиск шаблонов пользовательских переменных
        preg_match('/userVar\(\'(.*)\'\)/isU', $matches[1], $userVars);
        if (!empty($userVars[1])) {
            return $app->getUserVar($userVars[1]);
        }

        // Поиск шаблонов переменных темы
        preg_match('/themeConfig\(\'(.*)\'\)/isU', $matches[1], $themeConfigMatches);
        if (!empty($themeConfigMatches[1]) && isset(\Cetera\Application::getInstance()->getServer()->getTheme()->config->{$themeConfigMatches[1]})) {
            return \Cetera\Application::getInstance()->getServer()->getTheme()->config->{$themeConfigMatches[1]};
        }

        // Поиск констант и параметров формы
        switch ($matches[1]) {
            case 'server.name':
                return $app->getServer()->fields['name'] ?? "";
            case 'server.url':
                return $_SERVER['HTTP_HOST'];
            case 'server.alias':
                return $app->getServer()->fields['alias'] ?? "";
            default:
                $_REQUEST[$matches[1]] ?? = "";
                if (is_array($_REQUEST[$matches[1]])) {
                    return implode(', ', $_REQUEST[$matches[1]]);
                } else {
                    return $_REQUEST[$matches[1]];
                }
        }
    }

    /**
     * Проверка на использование CAPTCHA
     *
     * @param $tmpl
     * @return bool
     */
    public static function useCaptcha($formTemplate)
    {
        if (strpos($formTemplate, 'captchaimg') !== false) {
            return self::$CAPTCHA_TYPE_SIMPLE;
        } elseif (strpos($formTemplate, 'captchastrongimg') !== false) {
            return self::$CAPTCHA_TYPE_STRONG;
        } elseif (strpos($formTemplate, 'recaptcha') !== false) {
            return self::$CAPTCHA_TYPE_RECAPTCHA;
        } else {
            return false;
        }
    }

}

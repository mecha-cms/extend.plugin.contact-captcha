<?php

Hook::set('shield.enter', function() use($site) {
    if ($site->is('page')) {
        Asset::set(__DIR__ . DS . 'lot' . DS . 'asset' . DS . 'css' . DS . 'contact.min.css');
    }
});

// Set unique ID as the form name
Config::set('_contact_captcha_id', sprintf('captcha:%x', crc32(date('Ymd')))); // valid for a day

function fn_contact_captcha($content) {
    if (($s = strpos($content, '<p class="form-contact-button">')) === false) {
        if (($s = strpos($content, '<p class="form-contact-button ')) === false) {
            return $content;
        }
    }
    $state = Plugin::state('contact-captcha');
    $type = $state['type'];
    $html = "";
    $id = Config::get('_contact_captcha_id');
    global $language;
    if ($captcha = call_user_func('Captcha::' . $type, ...array_merge(['contact'], (array) $state['types'][$type]))) {
        $html .= '<div class="form-contact-input form-contact-input:captcha form-contact-input:captcha-' . $type . ' p">';
        $html .= '<label for="form-contact-input:captcha">';
        $html .= $language->captcha;
        $html .= '</label>';
        $html .= '<div>';
        HTTP::delete('post', $id); // always clear the cache value
        $html .= $type !== 'token' ? $captcha . ' ' . Form::text($id, null, null, [
            'class[]' => ['input'],
            'id' => 'form-contact-input:captcha',
            'required' => true,
            'autocomplete' => 'off'
        ]) : Form::check($id, $captcha, false, $language->captcha_token_check, [
            'class[]' => ['input', 'captcha'],
            'id' => 'form-contact-input:captcha'
        ]);
        $html .= '</div>';
        $html .= '</div>';
        return substr($content, 0, $s) . $html . substr($content, $s);
    }
    return $content;
}

// Apply captcha only for inactive user(s)
if (!Extend::exist('user') || !Is::user()) {

    // Set through `shield.yield` hook instead of `shield.get`
    // because cookie data must be sent before HTTP header(s) set,
    // and `shield.yield` can be used to make sure that the output
    // buffer started before any other output buffer(s)
    Hook::set('shield.yield', 'fn_contact_captcha');

    $state = Plugin::state('contact');
    Route::lot('%*%/' . $state['path'], function() use($state, $url) {
        $id = Config::get('_contact_captcha_id');
        if (HTTP::is('post') && Captcha::check(HTTP::post($id), 'contact') === false) {
            $s = Plugin::state('contact-captcha', 'type');
            if ($s === 'token') {
                $s .= '_check';
            }
            Message::error('captcha' . ($s ? '_' . $s : ""));
            HTTP::save('post');
            Guardian::kick(Path::D($url->clean) . $url->query . '#' . $state['anchor'][1]);
        }
    });

}
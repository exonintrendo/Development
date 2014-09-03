<?php
/**
 * Created by PhpStorm.
 * User: exonintrendo
 * Date: 9/3/14
 * Time: 12:21 PM
 */

namespace Primer\Utility;

use Primer\Component\RequestComponent;
use Primer\Component\SessionComponent;

/*
 * Creates, renders and checks the captcha.
 * This class uses the free, "dirty" Times New Yorker font
 * @see http://www.dafont.com/times-new-yorker.font
 *
 * This captcha implementation is also inspired by https://github.com/dgmike/captcha
 *
 */

class Security
{
    private $_session;
    private $_request;

    /*
     * Configuration for: Hashing strength
     * This is the place where you define the strength of your password hashing/salting
     *
     * To make password encryption very safe and future-proof, the PHP 5.5 hashing/salting functions
     * come with a clever so called COST FACTOR. This number defines the base-2 logarithm of the rounds of hashing,
     * something like 2^12 if your cost factor is 12. By the way, 2^12 would be 4096 rounds of hashing, doubling the
     * round with each increase of the cost factor and therefore doubling the CPU power it needs.
     * Currently, in 2013, the developers of this functions have chosen a cost factor of 10, which fits most standard
     * server setups. When time goes by and server power becomes much more powerful, it might be useful to increase
     * the cost factor, to make the password hashing one step more secure. Have a look here
     * (@see https://github.com/panique/php-users/wiki/Which-hashing-&-salting-algorithm-should-be-used-%3F)
     * in the BLOWFISH benchmark table to get an idea how this factor behaves. For most people this is irrelevant,
     * but after some years this might be very very useful to keep the encryption of your database up to date.
     *
     * Remember: Every time a user registers or tries to log in (!) this calculation will be done.
     * Don't change this if you don't know what you do.
     *
     * To get more information about the best cost factor please have a look here
     * @see http://stackoverflow.com/q/4443476/1114320
     */
    private $_hashCostFactor = 10;

    public function __construct(
        SessionComponent $session,
        RequestComponent $request
    ) {
        $this->_session = $session;
        $this->_request = $request;
    }

    /**
     * crypt the user's password with the PHP 5.5's password_hash() function, results in a 60 character hash string
     * the PASSWORD_DEFAULT constant is defined by the PHP 5.5, or if you are using PHP 5.3/5.4, by the password hashing
     * compatibility library. the third parameter looks a little bit shitty, but that's how those PHP 5.5 functions
     * want the parameter: as an array with, currently only used with 'cost' => XX.
     *
     * @param $string
     *
     * @return bool|false|string
     */
    public function hash($string)
    {
        return password_hash(
            $string,
            PASSWORD_DEFAULT,
            array('cost' => $this->_hashCostFactor)
        );
    }

    public function verifyHash($string, $hash)
    {
        return password_verify($string, $hash);
    }

    /**
     * generates the captcha string
     */
    public function generateCaptcha()
    {
        // create set of usage characters
        $letters = array_merge(range('A', 'Z'), range(2, 9));
        unset($letters[array_search('O', $letters)]);
        unset($letters[array_search('Q', $letters)]);
        unset($letters[array_search('I', $letters)]);
        unset($letters[array_search('5', $letters)]);
        unset($letters[array_search('S', $letters)]);
        shuffle($letters);
        $selected_letters = array_slice($letters, 0, 4);
        $secure_text = implode('', $selected_letters);

        // write the 4 selected letters into a SESSION variable
        $this->_session->write('captcha', $secure_text);
    }

    /**
     * renders an image to the browser
     *
     * TODO: this is not really good coding style, as this does return
     * something "binary" (correct me please if i'm talking bullshit).
     * maybe there's a cleaner method to do this ? all captcha scripts
     * i checked are doing it like this
     */
    public function showCaptcha()
    {
        // get letters from SESSION, split them, create array of letters
        $letters = str_split($this->_session->read('captcha'));

        // begin to create the image with PHP's GD tools
        $im = imagecreatetruecolor(150, 70);
        // TODO: error handling if creating images fails
        //or die("Cannot Initialize new GD image stream");

        $bg = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $bg);

        // create background with 1000 short lines
        /*
        for($i=0;$i<1000;$i++) {
            $lines = imagecolorallocate($im, rand(200, 220), rand(200, 220), rand(200, 220));
            $start_x = rand(0,150);
            $start_y = rand(0,70);
            $end_x = $start_x + rand(0,5);
            $end_y = $start_y + rand(0,5);
            imageline($im, $start_x, $start_y, $end_x, $end_y, $lines);
        }
        */

        // create letters. for more info on how this works, please
        // @see php.net/manual/en/function.imagefttext.php
        // TODO: put the font path into the config
        $i = 0;
        foreach ($letters as $letter) {
            $text_color = imagecolorallocate(
                $im,
                rand(0, 100),
                rand(10, 100),
                rand(0, 100)
            );
            // font-path relative to the index.php of the entire app
            imagefttext(
                $im,
                35,
                rand(-10, 10),
                20 + ($i * 30) + rand(-5, +5),
                35 + rand(10, 30),
                $text_color,
                APP_ROOT . '/public/fonts/times_new_yorker.ttf',
                $letter
            );
            $i++;
        }

        // send http-header to prevent image caching (so we always see a fresh captcha image)
        header('Content-type: image/png');
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, proxy-revalidate');

        // send image to browser, destroy image from php "cache"
        imagepng($im);
        imagedestroy($im);
    }

    /**
     * simply checks if the entered captcha is the same like the one from the rendered image (=SESSION)
     */
    public function checkCaptcha($check = null)
    {
        // a little bit simple, but it will work for a basic captcha system
        // TODO: write stuff like that simpler with ternary operators
        if ($check === null) {
            if (strtolower(
                    $this->_request->post()->get('captcha')
                ) == strtolower(
                    $this->_session->read('captcha')
                )
            ) {
                return true;
            } else {
                return false;
            }
        } else {
            if (strtolower($check) == strtolower(
                    $this->_session->read('captcha')
                )
            ) {
                return true;
            } else {
                return false;
            }
        }
    }
}
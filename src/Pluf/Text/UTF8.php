<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Plume Framework, a simple PHP Application Framework.
# Copyright (C) 2001-2007 Loic d'Anterroches and contributors.
#
# Plume Framework is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License, or
# (at your option) any later version.
#
# Plume Framework is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * UTF8 helper functions
 *
 * Original file coming from DokuWiki. Updated as we consider that the
 * multibytes functions are always available and wrapped in a class.
 *
 * @license    LGPL (http://www.gnu.org/copyleft/lesser.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */


class Pluf_Text_UTF8
{
    /**
     * URL-Encode a filename/URL to allow unicodecharacters
     *
     * Slashes are not encoded
     *
     * When the second parameter is true the string will
     * be encoded only if non ASCII characters are detected -
     * This makes it safe to run it multiple times on the
     * same string (default is true)
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @see    urlencode
     */
    public static function filename($file, $safe=true)
    {
        if ($safe && preg_match('#^[a-zA-Z0-9/_\-.%]+$#', $file)) {
            return $file;
        }
        return str_replace('%2F', '/', urlencode($file));
    }

    /**
     * Checks if a string contains 7bit ASCII only
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public static function is_ascii($str) 
    {
        $n = strlen($str);
        for ($i=0; $i<$n; $i++) {
            if (ord($str{$i}) >127) return false;
        }
        return true;
    }

    /**
     * Strips all highbyte chars
     *
     * Returns a pure ASCII7 string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public static function strip($str) 
    {
        $ascii = '';
        $n = strlen($str);
        for ($i=0; $i<$n; $i++) {
            if (ord($str{$i}) < 128) {
                $ascii .= $str{$i};
            }
        }
        return $ascii;
    }

    /**
     * Tries to detect if a string is in Unicode encoding
     *
     * @author <bmorel@ssi.fr>
     * @link   http://www.php.net/manual/en/function.utf8-encode.php
     */
    public static function check($str) 
    {
        $k = strlen($str);
        for ($i=0; $i<$k; $i++) {
            $b = ord($str[$i]);
            if ($b < 0x80) continue; // 0bbbbbbb
            elseif (($b & 0xE0) == 0xC0) $n = 1; // 110bbbbb
            elseif (($b & 0xF0) == 0xE0) $n = 2; // 1110bbbb
            elseif (($b & 0xF8) == 0xF0) $n = 3; // 11110bbb
            elseif (($b & 0xFC) == 0xF8) $n = 4; // 111110bb
            elseif (($b & 0xFE) == 0xFC) $n = 5; // 1111110b
            else return false; // Does not match any model
            for ($j=0; $j<$n; $j++) { // n bytes matching 10bbbbbb follow ?
                if ((++$i == $k) || ((ord($str[$i]) & 0xC0) != 0x80)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Detect if a string is in a Russian charset.
     *
     * This should be used when the mb_string detection encoding is
     * failing. For example:
     *
     * <pre>
     * $encoding = mb_detect_encoding($string, mb_detect_order(), true);
     * if ($encoding == false) {
     *     $encoding = Pluf_Text_UTF8::detect_cyr_charset($string);
     * }
     * </pre>
     *
     * @link http://forum.php.su/topic.php?forum=1&topic=1346
     *
     * @param string 
     * @return string Possible Russian encoding
     */
    public static function detect_cyr_charset($str) 
    {
        $charsets = array(
                          'KOI8-R' => 0,
                          'Windows-1251' => 0,
                          'CP-866' => 0,
                          'ISO-8859-5' => 0,
                          );
        $length = strlen($str);
        for ($i=0; $i<$length; $i++) {
            $char = ord($str[$i]);
            //non-russian characters
            if ($char < 128 || $char > 256) continue;

            //CP866
            if (($char > 159 && $char < 176) || ($char > 223 && $char < 242))
                $charsets['CP-866']+=3;
            if (($char > 127 && $char < 160)) $charsets['CP-866']+=1;

            //KOI8-R
            if (($char > 191 && $char < 223)) $charsets['KOI8-R']+=3;
            if (($char > 222 && $char < 256)) $charsets['KOI8-R']+=1;

            //WIN-1251
            if ($char > 223 && $char < 256) $charsets['Windows-1251']+=3;
            if ($char > 191 && $char < 224) $charsets['Windows-1251']+=1;

            //ISO-8859-5
            if ($char > 207 && $char < 240) $charsets['ISO-8859-5']+=3;
            if ($char > 175 && $char < 208) $charsets['ISO-8859-5']+=1;

        }
        arsort($charsets);
        return key($charsets);
    }


    /**
     * Replace accented UTF-8 characters by unaccented ASCII-7 equivalents.
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public static function deaccent($string)
    {
        return strtr($string, self::accents());
    }

    /**
     * Romanize a non-latin string
     *
     * FIXME
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public static function romanize($string)
    {
        if (self::is_ascii($string)) return $string;
        return strtr($string, self::romanization());
    }

    /**
     * Removes special characters (nonalphanumeric) from a UTF-8 string
     *
     * This function adds the controlchars 0x00 to 0x19 to the array of
     * stripped chars (they are not included in $UTF8_SPECIAL_CHARS)
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @param  string $string     The UTF8 string to strip of special chars
     * @param  string $repl       Replace special with this string
     * @param  string $additional Additional chars to strip (used in regexp char class)
     */
    function stripspecials($string, $repl='', $additional='')
    {
        static $specials = null;
        if (is_null($specials)) {
            $specials = preg_quote(self::special_chars(), '/');
        }
        return preg_replace('/['.$additional.'\x00-\x19'.$specials.']/u',$repl,$string);
    }


    /**
     * UTF-8 lookup table for lower case accented letters
     *
     * This lookuptable defines replacements for accented characters from the ASCII-7
     * range. This are lower case letters only.
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @see    utf8_deaccent()
     */
    public static function accents()
    {
        return array(
                     'à' => 'a', 'ô' => 'o', 'ď' => 'd', 'ḟ' => 'f', 
                     'ë' => 'e', 'š' => 's', 'ơ' => 'o', 'ß' => 'ss', 
                     'ă' => 'a', 'ř' => 'r', 'ț' => 't', 'ň' => 'n', 
                     'ā' => 'a', 'ķ' => 'k', 'ŝ' => 's', 'ỳ' => 'y', 
                     'ņ' => 'n', 'ĺ' => 'l', 'ħ' => 'h', 'ṗ' => 'p', 
                     'ó' => 'o', 'ú' => 'u', 'ě' => 'e', 'é' => 'e', 
                     'ç' => 'c', 'ẁ' => 'w', 'ċ' => 'c', 'õ' => 'o',
                     'ṡ' => 's', 'ø' => 'o', 'ģ' => 'g', 'ŧ' => 't', 
                     'ș' => 's', 'ė' => 'e', 'ĉ' => 'c', 'ś' => 's', 
                     'î' => 'i', 'ű' => 'u', 'ć' => 'c', 'ę' => 'e', 
                     'ŵ' => 'w', 'ṫ' => 't', 'ū' => 'u', 'č' => 'c', 
                     'ö' => 'oe', 'è' => 'e', 'ŷ' => 'y', 'ą' => 'a', 
                     'ł' => 'l', 'ų' => 'u', 'ů' => 'u', 'ş' => 's', 
                     'ğ' => 'g', 'ļ' => 'l', 'ƒ' => 'f', 'ž' => 'z',
                     'ẃ' => 'w', 'ḃ' => 'b', 'å' => 'a', 'ì' => 'i', 
                     'ï' => 'i', 'ḋ' => 'd', 'ť' => 't', 'ŗ' => 'r', 
                     'ä' => 'ae', 'í' => 'i', 'ŕ' => 'r', 'ê' => 'e', 
                     'ü' => 'ue', 'ò' => 'o', 'ē' => 'e', 'ñ' => 'n', 
                     'ń' => 'n', 'ĥ' => 'h', 'ĝ' => 'g', 'đ' => 'd', 
                     'ĵ' => 'j', 'ÿ' => 'y', 'ũ' => 'u', 'ŭ' => 'u', 
                     'ư' => 'u', 'ţ' => 't', 'ý' => 'y', 'ő' => 'o',
                     'â' => 'a', 'ľ' => 'l', 'ẅ' => 'w', 'ż' => 'z', 
                     'ī' => 'i', 'ã' => 'a', 'ġ' => 'g', 'ṁ' => 'm', 
                     'ō' => 'o', 'ĩ' => 'i', 'ù' => 'u', 'į' => 'i', 
                     'ź' => 'z', 'á' => 'a', 'û' => 'u', 'þ' => 'th', 
                     'ð' => 'dh', 'æ' => 'ae', 'µ' => 'u', 'ĕ' => 'e',
                     'À' => 'A', 'Ô' => 'O', 'Ď' => 'D', 'Ḟ' => 'F', 
                     'Ë' => 'E', 'Š' => 'S', 'Ơ' => 'O', 'Ă' => 'A', 
                     'Ř' => 'R', 'Ț' => 'T', 'Ň' => 'N', 'Ā' => 'A', 
                     'Ķ' => 'K', 'Ŝ' => 'S', 'Ỳ' => 'Y', 'Ņ' => 'N', 
                     'Ĺ' => 'L', 'Ħ' => 'H', 'Ṗ' => 'P', 'Ó' => 'O',
                     'Ú' => 'U', 'Ě' => 'E', 'É' => 'E', 'Ç' => 'C', 
                     'Ẁ' => 'W', 'Ċ' => 'C', 'Õ' => 'O', 'Ṡ' => 'S', 
                     'Ø' => 'O', 'Ģ' => 'G', 'Ŧ' => 'T', 'Ș' => 'S', 
                     'Ė' => 'E', 'Ĉ' => 'C', 'Ś' => 'S', 'Î' => 'I', 
                     'Ű' => 'U', 'Ć' => 'C', 'Ę' => 'E', 'Ŵ' => 'W', 
                     'Ṫ' => 'T', 'Ū' => 'U', 'Č' => 'C', 'Ö' => 'Oe', 
                     'È' => 'E', 'Ŷ' => 'Y', 'Ą' => 'A', 'Ł' => 'L',
                     'Ų' => 'U', 'Ů' => 'U', 'Ş' => 'S', 'Ğ' => 'G', 
                     'Ļ' => 'L', 'Ƒ' => 'F', 'Ž' => 'Z', 'Ẃ' => 'W', 
                     'Ḃ' => 'B', 'Å' => 'A', 'Ì' => 'I', 'Ï' => 'I', 
                     'Ḋ' => 'D', 'Ť' => 'T', 'Ŗ' => 'R', 'Ä' => 'Ae', 
                     'Í' => 'I', 'Ŕ' => 'R', 'Ê' => 'E', 'Ü' => 'Ue', 
                     'Ò' => 'O', 'Ē' => 'E', 'Ñ' => 'N', 'Ń' => 'N', 
                     'Ĥ' => 'H', 'Ĝ' => 'G', 'Đ' => 'D', 'Ĵ' => 'J',
                     'Ÿ' => 'Y', 'Ũ' => 'U', 'Ŭ' => 'U', 'Ư' => 'U', 
                     'Ţ' => 'T', 'Ý' => 'Y', 'Ő' => 'O', 'Â' => 'A', 
                     'Ľ' => 'L', 'Ẅ' => 'W', 'Ż' => 'Z', 'Ī' => 'I', 
                     'Ã' => 'A', 'Ġ' => 'G', 'Ṁ' => 'M', 'Ō' => 'O', 
                     'Ĩ' => 'I', 'Ù' => 'U', 'Į' => 'I', 'Ź' => 'Z', 
                     'Á' => 'A', 'Û' => 'U', 'Þ' => 'Th', 'Ð' => 'Dh', 
                     'Æ' => 'Ae', 'Ĕ' => 'E',
                     );
    }

    public static function special_chars()
    {
        return "\x1A".' !"#$%&\'()+,/;<=>?@[\]^`{|}~�'.
        '� ¡¢£¤¥¦§¨©ª«¬­®¯°±²³´µ¶·¸¹º»¼½�'.
        '�¿×÷ˇ˘˙˚˛˜˝̣̀́̃̉΄΅·βφϑϒϕϖְֱֲֳִֵֶַָֹֻּֽ־ֿ�'.
        '�ׁׂ׃׳״،؛؟ـًٌٍَُِّْ٪฿‌‍‎‏–—―‗‘’‚“”�'.
        '��†‡•…‰′″‹›⁄₧₪₫€№℘™Ωℵ←↑→↓↔↕↵'.
        '⇐⇑⇒⇓⇔∀∂∃∅∆∇∈∉∋∏∑−∕∗∙√∝∞∠∧∨�'.
        '�∪∫∴∼≅≈≠≡≤≥⊂⊃⊄⊆⊇⊕⊗⊥⋅⌐⌠⌡〈〉⑩─�'.
        '��┌┐└┘├┤┬┴┼═║╒╓╔╕╖╗╘╙╚╛╜╝╞╟╠'.
        '╡╢╣╤╥╦╧╨╩╪╫╬▀▄█▌▐░▒▓■▲▼◆◊●�'.
        '�★☎☛☞♠♣♥♦✁✂✃✄✆✇✈✉✌✍✎✏✐✑✒✓✔✕�'.
        '��✗✘✙✚✛✜✝✞✟✠✡✢✣✤✥✦✧✩✪✫✬✭✮✯✰✱'.
        '✲✳✴✵✶✷✸✹✺✻✼✽✾✿❀❁❂❃❄❅❆❇❈❉❊❋�'.
        '�❏❐❑❒❖❘❙❚❛❜❝❞❡❢❣❤❥❦❧❿➉➓➔➘➙➚�'.
        '��➜➝➞➟➠➡➢➣➤➥➦➧➨➩➪➫➬➭➮➯➱➲➳➴➵➶'.
        '➷➸➹➺➻➼➽➾'.
        '　、。〃〈〉《》「」『』【】〒〔〕〖〗〘〙〚〛〶'.
        '�'.
        '�ﹼﹽ'.
        '！＂＃＄％＆＇（）＊＋，－．／：；＜＝＞？＠［＼］＾｀｛｜｝～'.
        '｟｠｡｢｣､･￠￡￢￣￤￥￦￨￩￪￫￬￭￮';
    }

    /**
     * Romanization lookup table
     *
     * This lookup tables provides a way to transform strings written
     * in a language different from the ones based upon latin letters
     * into plain ASCII.
     *
     * Please note: this is not a scientific transliteration table. It
     * only works oneway from nonlatin to ASCII and it works by simple
     * character replacement only. Specialities of each language are
     * not supported.
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Vitaly Blokhin <vitinfo@vitn.com>
     * @link   http://www.uconv.com/translit.htm
     * @author Bisqwit <bisqwit@iki.fi>
     * @link   http://kanjidict.stc.cx/hiragana.php?src=2
     * @link   http://www.translatum.gr/converter/greek-transliteration.htm
     * @link   http://en.wikipedia.org/wiki/Royal_Thai_General_System_of_Transcription
     * @link   http://www.btranslations.com/resources/romanization/korean.asp
     * @author Arthit Suriyawongkul <arthit@gmail.com>
     * @author Denis Scheither <amorphis@uni-bremen.de>
     */
    public static function romanization()
    {
        return array(
       //russian cyrillic
       'а'=>'a','А'=>'A','б'=>'b','Б'=>'B','в'=>'v','В'=>'V','г'=>'g','Г'=>'G',
       'д'=>'d','Д'=>'D','е'=>'e','Е'=>'E','ё'=>'jo','Ё'=>'Jo','ж'=>'zh','Ж'=>'Zh',
       'з'=>'z','З'=>'Z','и'=>'i','И'=>'I','й'=>'j','Й'=>'J','к'=>'k','К'=>'K',
       'л'=>'l','Л'=>'L','м'=>'m','М'=>'M','н'=>'n','Н'=>'N','о'=>'o','О'=>'O',
       'п'=>'p','П'=>'P','р'=>'r','Р'=>'R','с'=>'s','С'=>'S','т'=>'t','Т'=>'T',
       'у'=>'u','У'=>'U','ф'=>'f','Ф'=>'F','х'=>'x','Х'=>'X','ц'=>'c','Ц'=>'C',
       'ч'=>'ch','Ч'=>'Ch','ш'=>'sh','Ш'=>'Sh','щ'=>'sch','Щ'=>'Sch','ъ'=>'',
       'Ъ'=>'','ы'=>'y','Ы'=>'Y','ь'=>'','Ь'=>'','э'=>'eh','Э'=>'Eh','ю'=>'ju',
       'Ю'=>'Ju','я'=>'ja','Я'=>'Ja',
       // Ukrainian cyrillic
       'Ґ'=>'Gh','ґ'=>'gh','Є'=>'Je','є'=>'je','І'=>'I','і'=>'i','Ї'=>'Ji','ї'=>'ji',
       // Georgian
       'ა'=>'a','ბ'=>'b','გ'=>'g','დ'=>'d','ე'=>'e','ვ'=>'v','ზ'=>'z','თ'=>'th',
       'ი'=>'i','კ'=>'p','ლ'=>'l','მ'=>'m','ნ'=>'n','ო'=>'o','პ'=>'p','ჟ'=>'zh',
       'რ'=>'r','ს'=>'s','ტ'=>'t','უ'=>'u','ფ'=>'ph','ქ'=>'kh','ღ'=>'gh','ყ'=>'q',
       'შ'=>'sh','ჩ'=>'ch','ც'=>'c','ძ'=>'dh','წ'=>'w','ჭ'=>'j','ხ'=>'x','ჯ'=>'jh',
       'ჰ'=>'xh',
       //Sanskrit
       'अ'=>'a','आ'=>'ah','इ'=>'i','ई'=>'ih','उ'=>'u','ऊ'=>'uh','ऋ'=>'ry',
       'ॠ'=>'ryh','ऌ'=>'ly','ॡ'=>'lyh','ए'=>'e','ऐ'=>'ay','ओ'=>'o','औ'=>'aw',
       'अं'=>'amh','अः'=>'aq','क'=>'k','ख'=>'kh','ग'=>'g','घ'=>'gh','ङ'=>'nh',
       'च'=>'c','छ'=>'ch','ज'=>'j','झ'=>'jh','ञ'=>'ny','ट'=>'tq','ठ'=>'tqh',
       'ड'=>'dq','ढ'=>'dqh','ण'=>'nq','त'=>'t','थ'=>'th','द'=>'d','ध'=>'dh',
       'न'=>'n','प'=>'p','फ'=>'ph','ब'=>'b','भ'=>'bh','म'=>'m','य'=>'z','र'=>'r',
       'ल'=>'l','व'=>'v','श'=>'sh','ष'=>'sqh','स'=>'s','ह'=>'x',
       //Hebrew
       'א'=>'a', 'ב'=>'b','ג'=>'g','ד'=>'d','ה'=>'h','ו'=>'v','ז'=>'z','ח'=>'kh','ט'=>'th',
       'י'=>'y','ך'=>'h','כ'=>'k','ל'=>'l','ם'=>'m','מ'=>'m','ן'=>'n','נ'=>'n',
       'ס'=>'s','ע'=>'ah','ף'=>'f','פ'=>'p','ץ'=>'c','צ'=>'c','ק'=>'q','ר'=>'r',
       'ש'=>'sh','ת'=>'t',
       //Arabic
       'ا'=>'a','ب'=>'b','ت'=>'t','ث'=>'th','ج'=>'g','ح'=>'xh','خ'=>'x','د'=>'d',
       'ذ'=>'dh','ر'=>'r','ز'=>'z','س'=>'s','ش'=>'sh','ص'=>'s\'','ض'=>'d\'',
       'ط'=>'t\'','ظ'=>'z\'','ع'=>'y','غ'=>'gh','ف'=>'f','ق'=>'q','ك'=>'k',
       'ل'=>'l','م'=>'m','ن'=>'n','ه'=>'x\'','و'=>'u','ي'=>'i',
       // Japanese hiragana
       // 3 character syllables, っ doubles the consonant after
       'っちゃ'=>'ccha','っちぇ'=>'cche','っちょ'=>'ccho','っちゅ'=>'cchu',
       'っびゃ'=>'bya','っびぇ'=>'bye','っびぃ'=>'byi','っびょ'=>'byo','っびゅ'=>'byu',
       'っちゃ'=>'cha','っちぇ'=>'che','っち'=>'chi','っちょ'=>'cho','っちゅ'=>'chu',
       'っひゃ'=>'hya','っひぇ'=>'hye','っひぃ'=>'hyi','っひょ'=>'hyo','っひゅ'=>'hyu',
       'っきゃ'=>'kya','っきぇ'=>'kye','っきぃ'=>'kyi','っきょ'=>'kyo','っきゅ'=>'kyu',
       'っぎゃ'=>'gya','っぎぇ'=>'gye','っぎぃ'=>'gyi','っぎょ'=>'gyo','っぎゅ'=>'gyu',
       'っみゃ'=>'mya','っみぇ'=>'mye','っみぃ'=>'myi','っみょ'=>'myo','っみゅ'=>'myu',
       'っにゃ'=>'nya','っにぇ'=>'nye','っにぃ'=>'nyi','っにょ'=>'nyo','っにゅ'=>'nyu',
       'っりゃ'=>'rya','っりぇ'=>'rye','っりぃ'=>'ryi','っりょ'=>'ryo','っりゅ'=>'ryu',
       'っしゃ'=>'sha','っしぇ'=>'she','っし'=>'shi','っしょ'=>'sho','っしゅ'=>'shu',
       // 2 character syllables - normal
       'ふぁ'=>'fa','ふぇ'=>'fe','ふぃ'=>'fi','ふぉ'=>'fo','ふ'=>'fu',
       'ヴぁ'=>'va','ヴぇ'=>'ve','ヴぃ'=>'vi','ヴぉ'=>'vo','ヴ'=>'vu',
       'びゃ'=>'bya','びぇ'=>'bye','びぃ'=>'byi','びょ'=>'byo','びゅ'=>'byu',
       'ちゃ'=>'cha','ちぇ'=>'che','ち'=>'chi','ちょ'=>'cho','ちゅ'=>'chu',
       'ひゃ'=>'hya','ひぇ'=>'hye','ひぃ'=>'hyi','ひょ'=>'hyo','ひゅ'=>'hyu',
       'きゃ'=>'kya','きぇ'=>'kye','きぃ'=>'kyi','きょ'=>'kyo','きゅ'=>'kyu',
       'ぎゃ'=>'gya','ぎぇ'=>'gye','ぎぃ'=>'gyi','ぎょ'=>'gyo','ぎゅ'=>'gyu',
       'みゃ'=>'mya','みぇ'=>'mye','みぃ'=>'myi','みょ'=>'myo','みゅ'=>'myu',
       'にゃ'=>'nya','にぇ'=>'nye','にぃ'=>'nyi','にょ'=>'nyo','にゅ'=>'nyu',
       'りゃ'=>'rya','りぇ'=>'rye','りぃ'=>'ryi','りょ'=>'ryo','りゅ'=>'ryu',
       'しゃ'=>'sha','しぇ'=>'she','し'=>'shi','しょ'=>'sho','しゅ'=>'shu',
       'じゃ'=>'ja','じぇ'=>'je','じ'=>'ji','じょ'=>'jo','じゅ'=>'ju',

       // 2 character syllables, っ doubles the consonant after
       'っば'=>'bba','っべ'=>'bbe','っび'=>'bbi','っぼ'=>'bbo','っぶ'=>'bbu',
       'っぱ'=>'ppa','っぺ'=>'ppe','っぴ'=>'ppi','っぽ'=>'ppo','っぷ'=>'ppu',
       'った'=>'tta','って'=>'tte','っち'=>'cchi','っと'=>'tto','っつ'=>'ttsu',
       'っだ'=>'dda','っで'=>'dde','っぢ'=>'ddi','っど'=>'ddo','っづ'=>'ddu',
       'っが'=>'gga','っげ'=>'gge','っぎ'=>'ggi','っご'=>'ggo','っぐ'=>'ggu',
       'っか'=>'kka','っけ'=>'kke','っき'=>'kki','っこ'=>'kko','っく'=>'kku',
       'っま'=>'mma','っめ'=>'mme','っみ'=>'mmi','っも'=>'mmo','っむ'=>'mmu',
       'っな'=>'nna','っね'=>'nne','っに'=>'nni','っの'=>'nno','っぬ'=>'nnu',
       'っら'=>'rra','っれ'=>'rre','っり'=>'rri','っろ'=>'rro','っる'=>'rru',
       'っさ'=>'ssa','っせ'=>'sse','っし'=>'sshi','っそ'=>'sso','っす'=>'ssu',
       'っざ'=>'zza','っぜ'=>'zze','っじ'=>'zzi','っぞ'=>'zzo','っず'=>'zzu',
  
       // 1 character syllabels
       'あ'=>'a','え'=>'e','い'=>'i','お'=>'o','う'=>'u','ん'=>'n',
       'は'=>'ha','へ'=>'he','ひ'=>'hi','ほ'=>'ho','ふ'=>'hu',
       'ば'=>'ba','べ'=>'be','び'=>'bi','ぼ'=>'bo','ぶ'=>'bu',
       'ぱ'=>'pa','ぺ'=>'pe','ぴ'=>'pi','ぽ'=>'po','ぷ'=>'pu',
       'た'=>'ta','て'=>'te','ち'=>'ti','と'=>'to','つ'=>'tu',
       'だ'=>'da','で'=>'de','ぢ'=>'di','ど'=>'do','づ'=>'du',
       'が'=>'ga','げ'=>'ge','ぎ'=>'gi','ご'=>'go','ぐ'=>'gu',
       'か'=>'ka','け'=>'ke','き'=>'ki','こ'=>'ko','く'=>'ku',
       'ま'=>'ma','め'=>'me','み'=>'mi','も'=>'mo','む'=>'mu',
       'な'=>'na','ね'=>'ne','に'=>'ni','の'=>'no','ぬ'=>'nu',
       'ら'=>'ra','れ'=>'re','り'=>'ri','ろ'=>'ro','る'=>'ru',
       'さ'=>'sa','せ'=>'se','し'=>'shi','そ'=>'so','す'=>'su',
       'わ'=>'wa','うぇ'=>'we','うぃ'=>'wi','を'=>'wo',
       'ざ'=>'za','ぜ'=>'ze','じ'=>'zi','ぞ'=>'zo','ず'=>'zu',
       'や'=>'ya','いぇ'=>'ye','よ'=>'yo','ゆ'=>'yu',

       // never seen one of those, but better save than sorry
       'でゃ'=>'dha','でぇ'=>'dhe','でぃ'=>'dhi','でょ'=>'dho','でゅ'=>'dhu',
       'どぁ'=>'dwa','どぇ'=>'dwe','どぃ'=>'dwi','どぉ'=>'dwo','どぅ'=>'dwu',
       'ぢゃ'=>'dya','ぢぇ'=>'dye','ぢぃ'=>'dyi','ぢょ'=>'dyo','ぢゅ'=>'dyu',
       'ふぁ'=>'fwa','ふぇ'=>'fwe','ふぃ'=>'fwi','ふぉ'=>'fwo','ふぅ'=>'fwu',
       'ふゃ'=>'fya','ふぇ'=>'fye','ふぃ'=>'fyi','ふょ'=>'fyo','ふゅ'=>'fyu',
       'ぴゃ'=>'pya','ぴぇ'=>'pye','ぴぃ'=>'pyi','ぴょ'=>'pyo','ぴゅ'=>'pyu',
       'すぁ'=>'swa','すぇ'=>'swe','すぃ'=>'swi','すぉ'=>'swo','すぅ'=>'swu',
       'てゃ'=>'tha','てぇ'=>'the','てぃ'=>'thi','てょ'=>'tho','てゅ'=>'thu',
       'つゃ'=>'tsa','つぇ'=>'tse','つぃ'=>'tsi','つょ'=>'tso','つ'=>'tsu',
       'とぁ'=>'twa','とぇ'=>'twe','とぃ'=>'twi','とぉ'=>'two','とぅ'=>'twu',
       'ヴゃ'=>'vya','ヴぇ'=>'vye','ヴぃ'=>'vyi','ヴょ'=>'vyo','ヴゅ'=>'vyu',
       'うぁ'=>'wha','うぇ'=>'whe','うぃ'=>'whi','うぉ'=>'who','うぅ'=>'whu',
       'ゑ'=>'wye','ゐ'=>'wyi',
       'じゃ'=>'zha','じぇ'=>'zhe','じぃ'=>'zhi','じょ'=>'zho','じゅ'=>'zhu',
       'じゃ'=>'zya','じぇ'=>'zye','じぃ'=>'zyi','じょ'=>'zyo','じゅ'=>'zyu',

       //  convert what's left (probably only kicks in when something's missing above
       'ぁ'=>'a','ぇ'=>'e','ぃ'=>'i','ぉ'=>'o','ぅ'=>'u',
       'ゃ'=>'ya','ょ'=>'yo','ゅ'=>'yu',

       // 'spare' characters from other romanization systems
       // 'だ'=>'da','で'=>'de','ぢ'=>'di','ど'=>'do','づ'=>'du',
       // 'ら'=>'la','れ'=>'le','り'=>'li','ろ'=>'lo','る'=>'lu',
       // 'さ'=>'sa','せ'=>'se','し'=>'si','そ'=>'so','す'=>'su',
       // 'ちゃ'=>'cya','ちぇ'=>'cye','ちぃ'=>'cyi','ちょ'=>'cyo','ちゅ'=>'cyu',
       //'じゃ'=>'jya','じぇ'=>'jye','じぃ'=>'jyi','じょ'=>'jyo','じゅ'=>'jyu',
       //'りゃ'=>'lya','りぇ'=>'lye','りぃ'=>'lyi','りょ'=>'lyo','りゅ'=>'lyu',
       //'しゃ'=>'sya','しぇ'=>'sye','しぃ'=>'syi','しょ'=>'syo','しゅ'=>'syu',
       //'ちゃ'=>'tya','ちぇ'=>'tye','ちぃ'=>'tyi','ちょ'=>'tyo','ちゅ'=>'tyu',
       //'し'=>'ci',,い'=>'yi','ぢ'=>'dzi',
       //'っじゃ'=>'jja','っじぇ'=>'jje','っじ'=>'jji','っじょ'=>'jjo','っじゅ'=>'jju',


       // Japanese katakana

       // 4 character syllables: ッ doubles the consonant after, ー doubles the vowel before (usualy written with macron, but we don't want that in our URLs)
       'ッビャー'=>'bbyaa','ッビェー'=>'bbyee','ッビィー'=>'bbyii','ッビョー'=>'bbyoo','ッビュー'=>'bbyuu',
       'ッピャー'=>'ppyaa','ッピェー'=>'ppyee','ッピィー'=>'ppyii','ッピョー'=>'ppyoo','ッピュー'=>'ppyuu',
       'ッキャー'=>'kkyaa','ッキェー'=>'kkyee','ッキィー'=>'kkyii','ッキョー'=>'kkyoo','ッキュー'=>'kkyuu',
       'ッギャー'=>'ggyaa','ッギェー'=>'ggyee','ッギィー'=>'ggyii','ッギョー'=>'ggyoo','ッギュー'=>'ggyuu',
       'ッミャー'=>'mmyaa','ッミェー'=>'mmyee','ッミィー'=>'mmyii','ッミョー'=>'mmyoo','ッミュー'=>'mmyuu',
       'ッニャー'=>'nnyaa','ッニェー'=>'nnyee','ッニィー'=>'nnyii','ッニョー'=>'nnyoo','ッニュー'=>'nnyuu',
       'ッリャー'=>'rryaa','ッリェー'=>'rryee','ッリィー'=>'rryii','ッリョー'=>'rryoo','ッリュー'=>'rryuu',
       'ッシャー'=>'sshaa','ッシェー'=>'sshee','ッシー'=>'sshii','ッショー'=>'sshoo','ッシュー'=>'sshuu',
       'ッチャー'=>'cchaa','ッチェー'=>'cchee','ッチー'=>'cchii','ッチョー'=>'cchoo','ッチュー'=>'cchuu',

       // 3 character syllables - doubled vowels
       'ファー'=>'faa','フェー'=>'fee','フィー'=>'fii','フォー'=>'foo',
       'フャー'=>'fyaa','フェー'=>'fyee','フィー'=>'fyii','フョー'=>'fyoo','フュー'=>'fyuu',
       'ヒャー'=>'hyaa','ヒェー'=>'hyee','ヒィー'=>'hyii','ヒョー'=>'hyoo','ヒュー'=>'hyuu',
       'ビャー'=>'byaa','ビェー'=>'byee','ビィー'=>'byii','ビョー'=>'byoo','ビュー'=>'byuu',
       'ピャー'=>'pyaa','ピェー'=>'pyee','ピィー'=>'pyii','ピョー'=>'pyoo','ピュー'=>'pyuu',
       'キャー'=>'kyaa','キェー'=>'kyee','キィー'=>'kyii','キョー'=>'kyoo','キュー'=>'kyuu',
       'ギャー'=>'gyaa','ギェー'=>'gyee','ギィー'=>'gyii','ギョー'=>'gyoo','ギュー'=>'gyuu',
       'ミャー'=>'myaa','ミェー'=>'myee','ミィー'=>'myii','ミョー'=>'myoo','ミュー'=>'myuu',
       'ニャー'=>'nyaa','ニェー'=>'nyee','ニィー'=>'nyii','ニョー'=>'nyoo','ニュー'=>'nyuu',
       'リャー'=>'ryaa','リェー'=>'ryee','リィー'=>'ryii','リョー'=>'ryoo','リュー'=>'ryuu',
       'シャー'=>'shaa','シェー'=>'shee','シー'=>'shii','ショー'=>'shoo','シュー'=>'shuu',
       'ジャー'=>'jaa','ジェー'=>'jee','ジー'=>'jii','ジョー'=>'joo','ジュー'=>'juu',
       'スァー'=>'swaa','スェー'=>'swee','スィー'=>'swii','スォー'=>'swoo','スゥー'=>'swuu',
       'デァー'=>'daa','デェー'=>'dee','ディー'=>'dii','デォー'=>'doo','デゥー'=>'duu',
       'チャー'=>'chaa','チェー'=>'chee','チー'=>'chii','チョー'=>'choo','チュー'=>'chuu',
       'ヂャー'=>'dyaa','ヂェー'=>'dyee','ヂィー'=>'dyii','ヂョー'=>'dyoo','ヂュー'=>'dyuu',
       'ツャー'=>'tsaa','ツェー'=>'tsee','ツィー'=>'tsii','ツョー'=>'tsoo','ツー'=>'tsuu',
       'トァー'=>'twaa','トェー'=>'twee','トィー'=>'twii','トォー'=>'twoo','トゥー'=>'twuu',
       'ドァー'=>'dwaa','ドェー'=>'dwee','ドィー'=>'dwii','ドォー'=>'dwoo','ドゥー'=>'dwuu',
       'ウァー'=>'whaa','ウェー'=>'whee','ウィー'=>'whii','ウォー'=>'whoo','ウゥー'=>'whuu',
       'ヴャー'=>'vyaa','ヴェー'=>'vyee','ヴィー'=>'vyii','ヴョー'=>'vyoo','ヴュー'=>'vyuu',
       'ヴァー'=>'vaa','ヴェー'=>'vee','ヴィー'=>'vii','ヴォー'=>'voo','ヴー'=>'vuu',
       'ウェー'=>'wee','ウィー'=>'wii',
       'イェー'=>'yee',

       // 3 character syllables - doubled consonants
       'ッビャ'=>'bbya','ッビェ'=>'bbye','ッビィ'=>'bbyi','ッビョ'=>'bbyo','ッビュ'=>'bbyu',
       'ッピャ'=>'ppya','ッピェ'=>'ppye','ッピィ'=>'ppyi','ッピョ'=>'ppyo','ッピュ'=>'ppyu',
       'ッキャ'=>'kkya','ッキェ'=>'kkye','ッキィ'=>'kkyi','ッキョ'=>'kkyo','ッキュ'=>'kkyu',
       'ッギャ'=>'ggya','ッギェ'=>'ggye','ッギィ'=>'ggyi','ッギョ'=>'ggyo','ッギュ'=>'ggyu',
       'ッミャ'=>'mmya','ッミェ'=>'mmye','ッミィ'=>'mmyi','ッミョ'=>'mmyo','ッミュ'=>'mmyu',
       'ッニャ'=>'nnya','ッニェ'=>'nnye','ッニィ'=>'nnyi','ッニョ'=>'nnyo','ッニュ'=>'nnyu',
       'ッリャ'=>'rrya','ッリェ'=>'rrye','ッリィ'=>'rryi','ッリョ'=>'rryo','ッリュ'=>'rryu',
       'ッシャ'=>'ssha','ッシェ'=>'sshe','ッシ'=>'sshi','ッショ'=>'ssho','ッシュ'=>'sshu',
       'ッチャ'=>'ccha','ッチェ'=>'cche','ッチ'=>'cchi','ッチョ'=>'ccho','ッチュ'=>'cchu',

       // 3 character syllables - doubled vowel and consonants
       'ッバー'=>'bbaa','ッベー'=>'bbee','ッビー'=>'bbii','ッボー'=>'bboo','ッブー'=>'bbuu',
       'ッパー'=>'ppaa','ッペー'=>'ppee','ッピー'=>'ppii','ッポー'=>'ppoo','ップー'=>'ppuu',
       'ッケー'=>'kkee','ッキー'=>'kkii','ッコー'=>'kkoo','ックー'=>'kkuu','ッカー'=>'kkaa',
       'ッガー'=>'ggaa','ッゲー'=>'ggee','ッギー'=>'ggii','ッゴー'=>'ggoo','ッグー'=>'gguu',
       'ッマー'=>'maa','ッメー'=>'mee','ッミー'=>'mii','ッモー'=>'moo','ッムー'=>'muu',
       'ッナー'=>'nnaa','ッネー'=>'nnee','ッニー'=>'nnii','ッノー'=>'nnoo','ッヌー'=>'nnuu',
       'ッラー'=>'rraa','ッレー'=>'rree','ッリー'=>'rrii','ッロー'=>'rroo','ッルー'=>'rruu',
       'ッサー'=>'ssaa','ッセー'=>'ssee','ッシー'=>'sshii','ッソー'=>'ssoo','ッスー'=>'ssuu',
       'ッザー'=>'zzaa','ッゼー'=>'zzee','ッジー'=>'zzii','ッゾー'=>'zzoo','ッズー'=>'zzuu',
       'ッター'=>'ttaa','ッテー'=>'ttee','ッチー'=>'chii','ットー'=>'ttoo','ッツー'=>'ttssuu',
       'ッダー'=>'ddaa','ッデー'=>'ddee','ッヂー'=>'ddii','ッドー'=>'ddoo','ッヅー'=>'dduu',

       // 2 character syllables - normal
       'ファ'=>'fa','フェ'=>'fe','フィ'=>'fi','フォ'=>'fo',
       'フャ'=>'fya','フェ'=>'fye','フィ'=>'fyi','フョ'=>'fyo','フュ'=>'fyu',
       'ヒャ'=>'hya','ヒェ'=>'hye','ヒィ'=>'hyi','ヒョ'=>'hyo','ヒュ'=>'hyu',
       'ビャ'=>'bya','ビェ'=>'bye','ビィ'=>'byi','ビョ'=>'byo','ビュ'=>'byu',
       'ピャ'=>'pya','ピェ'=>'pye','ピィ'=>'pyi','ピョ'=>'pyo','ピュ'=>'pyu',
       'キャ'=>'kya','キェ'=>'kye','キィ'=>'kyi','キョ'=>'kyo','キュ'=>'kyu',
       'ギャ'=>'gya','ギェ'=>'gye','ギィ'=>'gyi','ギョ'=>'gyo','ギュ'=>'gyu',
       'ミャ'=>'mya','ミェ'=>'mye','ミィ'=>'myi','ミョ'=>'myo','ミュ'=>'myu',
       'ニャ'=>'nya','ニェ'=>'nye','ニィ'=>'nyi','ニョ'=>'nyo','ニュ'=>'nyu',
       'リャ'=>'rya','リェ'=>'rye','リィ'=>'ryi','リョ'=>'ryo','リュ'=>'ryu',
       'シャ'=>'sha','シェ'=>'she','シ'=>'shi','ショ'=>'sho','シュ'=>'shu',
       'ジャ'=>'ja','ジェ'=>'je','ジ'=>'ji','ジョ'=>'jo','ジュ'=>'ju',
       'スァ'=>'swa','スェ'=>'swe','スィ'=>'swi','スォ'=>'swo','スゥ'=>'swu',
       'デァ'=>'da','デェ'=>'de','ディ'=>'di','デォ'=>'do','デゥ'=>'du',
       'チャ'=>'cha','チェ'=>'che','チ'=>'chi','チョ'=>'cho','チュ'=>'chu',
       'ヂャ'=>'dya','ヂェ'=>'dye','ヂィ'=>'dyi','ヂョ'=>'dyo','ヂュ'=>'dyu',
       'ツャ'=>'tsa','ツェ'=>'tse','ツィ'=>'tsi','ツョ'=>'tso','ツ'=>'tsu',
       'トァ'=>'twa','トェ'=>'twe','トィ'=>'twi','トォ'=>'two','トゥ'=>'twu',
       'ドァ'=>'dwa','ドェ'=>'dwe','ドィ'=>'dwi','ドォ'=>'dwo','ドゥ'=>'dwu',
       'ウァ'=>'wha','ウェ'=>'whe','ウィ'=>'whi','ウォ'=>'who','ウゥ'=>'whu',
       'ヴャ'=>'vya','ヴェ'=>'vye','ヴィ'=>'vyi','ヴョ'=>'vyo','ヴュ'=>'vyu',
       'ヴァ'=>'va','ヴェ'=>'ve','ヴィ'=>'vi','ヴォ'=>'vo','ヴ'=>'vu',
       'ウェ'=>'we','ウィ'=>'wi',
       'イェ'=>'ye',

       // 2 character syllables - doubled vocal
       'アー'=>'aa','エー'=>'ee','イー'=>'ii','オー'=>'oo','ウー'=>'uu',
       'ダー'=>'daa','デー'=>'dee','ヂー'=>'dii','ドー'=>'doo','ヅー'=>'duu',
       'ハー'=>'haa','ヘー'=>'hee','ヒー'=>'hii','ホー'=>'hoo','フー'=>'fuu',
       'バー'=>'baa','ベー'=>'bee','ビー'=>'bii','ボー'=>'boo','ブー'=>'buu',
       'パー'=>'paa','ペー'=>'pee','ピー'=>'pii','ポー'=>'poo','プー'=>'puu',
       'ケー'=>'kee','キー'=>'kii','コー'=>'koo','クー'=>'kuu','カー'=>'kaa',
       'ガー'=>'gaa','ゲー'=>'gee','ギー'=>'gii','ゴー'=>'goo','グー'=>'guu',
       'マー'=>'maa','メー'=>'mee','ミー'=>'mii','モー'=>'moo','ムー'=>'muu',
       'ナー'=>'naa','ネー'=>'nee','ニー'=>'nii','ノー'=>'noo','ヌー'=>'nuu',
       'ラー'=>'raa','レー'=>'ree','リー'=>'rii','ロー'=>'roo','ルー'=>'ruu',
       'サー'=>'saa','セー'=>'see','シー'=>'shii','ソー'=>'soo','スー'=>'suu',
       'ザー'=>'zaa','ゼー'=>'zee','ジー'=>'zii','ゾー'=>'zoo','ズー'=>'zuu',
       'ター'=>'taa','テー'=>'tee','チー'=>'chii','トー'=>'too','ツー'=>'tsuu',
       'ワー'=>'waa','ヲー'=>'woo',
       'ヤー'=>'yaa','ヨー'=>'yoo','ユー'=>'yuu',
       'ヱー'=>'wyee','ヰー'=>'wyii',
       'ヵー'=>'kaa','ヶー'=>'kee',

       // 2 character syllables - doubled consonants
       'ッバ'=>'bba','ッベ'=>'bbe','ッビ'=>'bbi','ッボ'=>'bbo','ッブ'=>'bbu',
       'ッパ'=>'ppa','ッペ'=>'ppe','ッピ'=>'ppi','ッポ'=>'ppo','ップ'=>'ppu',
       'ッケ'=>'kke','ッキ'=>'kki','ッコ'=>'kko','ック'=>'kku','ッカ'=>'kka',
       'ッガ'=>'gga','ッゲ'=>'gge','ッギ'=>'ggi','ッゴ'=>'ggo','ッグ'=>'ggu',
       'ッマ'=>'ma','ッメ'=>'me','ッミ'=>'mi','ッモ'=>'mo','ッム'=>'mu',
       'ッナ'=>'nna','ッネ'=>'nne','ッニ'=>'nni','ッノ'=>'nno','ッヌ'=>'nnu',
       'ッラ'=>'rra','ッレ'=>'rre','ッリ'=>'rri','ッロ'=>'rro','ッル'=>'rru',
       'ッサ'=>'ssa','ッセ'=>'sse','ッシ'=>'sshi','ッソ'=>'sso','ッス'=>'ssu',
       'ッザ'=>'zza','ッゼ'=>'zze','ッジ'=>'zzi','ッゾ'=>'zzo','ッズ'=>'zzu',
       'ッタ'=>'tta','ッテ'=>'tte','ッチ'=>'chi','ット'=>'tto','ッツ'=>'ttssu',
       'ッダ'=>'dda','ッデ'=>'dde','ッヂ'=>'ddi','ッド'=>'ddo','ッヅ'=>'ddu',

       // 1 character syllables
       'ア'=>'a','エ'=>'e','イ'=>'i','オ'=>'o','ウ'=>'u','ン'=>'n',
       'ハ'=>'ha','ヘ'=>'he','ヒ'=>'hi','ホ'=>'ho','フ'=>'fu',
       'バ'=>'ba','ベ'=>'be','ビ'=>'bi','ボ'=>'bo','ブ'=>'bu',
       'パ'=>'pa','ペ'=>'pe','ピ'=>'pi','ポ'=>'po','プ'=>'pu',
       'ケ'=>'ke','キ'=>'ki','コ'=>'ko','ク'=>'ku','カ'=>'ka',
       'ガ'=>'ga','ゲ'=>'ge','ギ'=>'gi','ゴ'=>'go','グ'=>'gu',
       'マ'=>'ma','メ'=>'me','ミ'=>'mi','モ'=>'mo','ム'=>'mu',
       'ナ'=>'na','ネ'=>'ne','ニ'=>'ni','ノ'=>'no','ヌ'=>'nu',
       'ラ'=>'ra','レ'=>'re','リ'=>'ri','ロ'=>'ro','ル'=>'ru',
       'サ'=>'sa','セ'=>'se','シ'=>'shi','ソ'=>'so','ス'=>'su',
       'ザ'=>'za','ゼ'=>'ze','ジ'=>'zi','ゾ'=>'zo','ズ'=>'zu',
       'タ'=>'ta','テ'=>'te','チ'=>'chi','ト'=>'to','ツ'=>'tsu',
       'ダ'=>'da','デ'=>'de','ヂ'=>'di','ド'=>'do','ヅ'=>'du',
       'ワ'=>'wa','ヲ'=>'wo',
       'ヤ'=>'ya','ヨ'=>'yo','ユ'=>'yu',
       'ヱ'=>'wye','ヰ'=>'wyi',
       'ヵ'=>'ka','ヶ'=>'ke',

       //  convert what's left (probably only kicks in when something's missing above
       'ァ'=>'a','ェ'=>'e','ィ'=>'i','ォ'=>'o','ゥ'=>'u',
       'ャ'=>'ya','ョ'=>'yo','ュ'=>'yu',

       // 'ラ'=>'la','レ'=>'le','リ'=>'li','ロ'=>'lo','ル'=>'lu',
       // 'チャ'=>'cya','チェ'=>'cye','チィ'=>'cyi','チョ'=>'cyo','チュ'=>'cyu',
       //'デャ'=>'dha','デェ'=>'dhe','ディ'=>'dhi','デョ'=>'dho','デュ'=>'dhu',
       // 'リャ'=>'lya','リェ'=>'lye','リィ'=>'lyi','リョ'=>'lyo','リュ'=>'lyu',
       // 'テャ'=>'tha','テェ'=>'the','ティ'=>'thi','テョ'=>'tho','テュ'=>'thu',
       //'ファ'=>'fwa','フェ'=>'fwe','フィ'=>'fwi','フォ'=>'fwo','フゥ'=>'fwu',
       //'チャ'=>'tya','チェ'=>'tye','チィ'=>'tyi','チョ'=>'tyo','チュ'=>'tyu',
       // 'ジャ'=>'jya','ジェ'=>'jye','ジィ'=>'jyi','ジョ'=>'jyo','ジュ'=>'jyu',
       // 'ジャ'=>'zha','ジェ'=>'zhe','ジィ'=>'zhi','ジョ'=>'zho','ジュ'=>'zhu',
       //'ジャ'=>'zya','ジェ'=>'zye','ジィ'=>'zyi','ジョ'=>'zyo','ジュ'=>'zyu',
       //'シャ'=>'sya','シェ'=>'sye','シィ'=>'syi','ショ'=>'syo','シュ'=>'syu',
       //'シ'=>'ci','フ'=>'hu',シ'=>'si','チ'=>'ti','ツ'=>'tu','イ'=>'yi','ヂ'=>'dzi',

       // "Greeklish"
       'Γ'=>'G','Δ'=>'E','Θ'=>'Th','Λ'=>'L','Ξ'=>'X','Π'=>'P','Σ'=>'S','Φ'=>'F','Ψ'=>'Ps',
       'γ'=>'g','δ'=>'e','θ'=>'th','λ'=>'l','ξ'=>'x','π'=>'p','σ'=>'s','φ'=>'f','ψ'=>'ps',

       // Thai
       'ก'=>'k','ข'=>'kh','ฃ'=>'kh','ค'=>'kh','ฅ'=>'kh','ฆ'=>'kh','ง'=>'ng','จ'=>'ch',
       'ฉ'=>'ch','ช'=>'ch','ซ'=>'s','ฌ'=>'ch','ญ'=>'y','ฎ'=>'d','ฏ'=>'t','ฐ'=>'th',
       'ฑ'=>'d','ฒ'=>'th','ณ'=>'n','ด'=>'d','ต'=>'t','ถ'=>'th','ท'=>'th','ธ'=>'th',
       'น'=>'n','บ'=>'b','ป'=>'p','ผ'=>'ph','ฝ'=>'f','พ'=>'ph','ฟ'=>'f','ภ'=>'ph',
       'ม'=>'m','ย'=>'y','ร'=>'r','ฤ'=>'rue','ฤๅ'=>'rue','ล'=>'l','ฦ'=>'lue',
       'ฦๅ'=>'lue','ว'=>'w','ศ'=>'s','ษ'=>'s','ส'=>'s','ห'=>'h','ฬ'=>'l','ฮ'=>'h',
       'ะ'=>'a','ั'=>'a','รร'=>'a','า'=>'a','ๅ'=>'a','ำ'=>'am','ํา'=>'am',
       'ิ'=>'i','ี'=>'i','ึ'=>'ue','ี'=>'ue','ุ'=>'u','ู'=>'u',
       'เ'=>'e','แ'=>'ae','โ'=>'o','อ'=>'o',
       'ียะ'=>'ia','ีย'=>'ia','ือะ'=>'uea','ือ'=>'uea','ัวะ'=>'ua','ัว'=>'ua',
       'ใ'=>'ai','ไ'=>'ai','ัย'=>'ai','าย'=>'ai','าว'=>'ao',
       'ุย'=>'ui','อย'=>'oi','ือย'=>'ueai','วย'=>'uai',
       'ิว'=>'io','็ว'=>'eo','ียว'=>'iao',
       '่'=>'','้'=>'','๊'=>'','๋'=>'','็'=>'',
       '์'=>'','๎'=>'','ํ'=>'','ฺ'=>'',
       'ๆ'=>'2','๏'=>'o','ฯ'=>'-','๚'=>'-','๛'=>'-', 
       '๐'=>'0','๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4',
       '๕'=>'5','๖'=>'6','๗'=>'7','๘'=>'8','๙'=>'9',

       // Korean
       'ㄱ'=>'k','ㅋ'=>'kh','ㄲ'=>'kk','ㄷ'=>'t','ㅌ'=>'th','ㄸ'=>'tt','ㅂ'=>'p',
       'ㅍ'=>'ph','ㅃ'=>'pp','ㅈ'=>'c','ㅊ'=>'ch','ㅉ'=>'cc','ㅅ'=>'s','ㅆ'=>'ss',
       'ㅎ'=>'h','ㅇ'=>'ng','ㄴ'=>'n','ㄹ'=>'l','ㅁ'=>'m', 'ㅏ'=>'a','ㅓ'=>'e','ㅗ'=>'o',
       'ㅜ'=>'wu','ㅡ'=>'u','ㅣ'=>'i','ㅐ'=>'ay','ㅔ'=>'ey','ㅚ'=>'oy','ㅘ'=>'wa','ㅝ'=>'we',
       'ㅟ'=>'wi','ㅙ'=>'way','ㅞ'=>'wey','ㅢ'=>'uy','ㅑ'=>'ya','ㅕ'=>'ye','ㅛ'=>'oy',
       'ㅠ'=>'yu','ㅒ'=>'yay','ㅖ'=>'yey',
                               );
    }
}
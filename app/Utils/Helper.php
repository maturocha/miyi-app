<?php

use App\User;

if (! function_exists('_asset')) {
    /**
     * Load the asset from our assets JSON file.
     *
     * @param string $subPath
     *
     * @return string $filePath
     */
    function _asset(string $subPath) : string
    {
        $assets = json_decode(@file_get_contents('assets.json'), true);

        return $assets[$subPath] ?? '';
    }
}

if (! function_exists('_token_payload')) {
    /**
     * Get the token bearer payload.
     *
     * @param string $authToken
     *
     * @return array
     */
    function _token_payload(string $authToken) : array
    {
        return [
            'auth_token' => $authToken,
            'token_type' => 'bearer',
            'expires_in' => auth()->guard('api')->factory()->getTTL() * 9600
        ];
    }
}

if (! function_exists('_test_user')) {
    /**
     * Login and get the then authenticated user.
     *
     * @return App\User
     */
    function _test_user()
    {
        auth()->guard('api')->login(User::first());

        return auth()->guard('api')->user();
    }
}

if (! function_exists('_clean_string')) {
    /**
     * Login and get the then authenticated user.
     *
     * @return App\User
     */
    function _clean_string($string)
    {
        $utf8 = array(
            '/[áàâãªä]/u'   =>   'a',
            '/[ÁÀÂÃÄ]/u'    =>   'A',
            '/[ÍÌÎÏ]/u'     =>   'I',
            '/[íìîï]/u'     =>   'i',
            '/[éèêë]/u'     =>   'e',
            '/[ÉÈÊË]/u'     =>   'E',
            '/[óòôõºö]/u'   =>   'o',
            '/[ÓÒÔÕÖ]/u'    =>   'o',
            '/[úùûü]/u'     =>   'u',
            '/[ÚÙÛÜ]/u'     =>   'u',
            '/ç/'           =>   'c',
            '/Ç/'           =>   'c',
            '/ñ/'           =>   'n',
            '/Ñ/'           =>   'n',
            '/–/'           =>   '-', // UTF-8 hyphen to "normal" hyphen
        );
        $string = preg_replace(array_keys($utf8), array_values($utf8), $string);
        $string = str_replace(array('[\', \']'), '', $string);
        $string = preg_replace('/\[.*\]/U', '', $string);
        $string = preg_replace('/&(amp;)?#?[a-z0-9]+;/i', '-', $string);
        $string = htmlentities($string, ENT_COMPAT, 'utf-8');
        $string = preg_replace('/&([a-z])(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig|quot|rsquo);/i', '\\1', $string );
        $string = preg_replace(array('/[^a-z0-9]/i', '/[-]+/') , '-', $string);
        return strtolower(trim($string, '-'));
    }
}

if (! function_exists('_keyword_to_sql_operator')) {
    /**
     * Convert a keyword to an SQL operator.
     *
     * @param string $keyword
     *
     * @return string $operator
     */
    function _to_sql_operator($keyword) : string
    {
        switch ($keyword) {
            case 'eqs':
                return '=';
            break;

            case 'neqs':
                return '!=';
            break;

            case 'gt':
                return '>';
            break;

            case 'lt':
                return '<';
            break;

            case 'gte':
                return '>=';
            break;

            case 'lte':
                return '<=';
            break;

            case 'like':
                return 'LIKE';
            break;

            case 'nlike':
                return 'NOT LIKE';
            break;

            default:
                return $keyword;
            break;
        }
    }
}

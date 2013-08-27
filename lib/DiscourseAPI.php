<?php

/**
  * Discourse API client for PHP
  *
  * This is the Discourse API client for PHP
  * This is a very experimental API implementation.
  *
  * @category  DiscourseAPI
  * @package   DiscourseAPI
  * @author    Original author DiscourseHosting <richard@discoursehosting.com>
  * @copyright 2013, DiscourseHosting.com
  * @license   http://www.gnu.org/licenses/gpl-2.0.html GPLv2 
  * @link      https://github.com/discoursehosting/discourse-api-php
  */

class DiscourseAPI
{
    private $_sessionKey = null;
    private $_protocol = 'http';
    private $_apiKey = null;
    private $_userName = null;
    private $_dcHostname = null;

    function __construct($dcHostname, $apiKey = null, $userName = null) 
    {
        $this->_dcHostname = $dcHostname;
        $this->_apiKey = $apiKey;
        $this->_userName = $userName;
    }

    private function _ensureSession($forceNew = false) 
    {
        if (($forceNew == false) && ($this->_sessionKey != null)) {
            return true;
        }

        $url = sprintf(
            '%s://%s?api_key=%s&api_username=%s', 
            $this->_protocol, $this->_dcHostname, $this->_apiKey, $this->_userName
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $out = curl_exec($ch);

        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $out = substr($out, 0, $hs);
        preg_match('/^Set-Cookie:\s*([^;]*)/mi', $out, $m);
        parse_str($m[1], $cookies);
        $this->_sessionKey = $cookies['_forum_session'];

        curl_close($ch);

        return ($rc == 200);
    }

    private function _getRequest($reqString, $paramArray = null)
    {
        if ($paramArray == null) {
            $paramArray = array();
        }
        $paramArray['api_key'] = $this->_apiKey;
        $paramArray['api_username'] = $this->_userName;
        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s?%s',
            $this->_protocol, 
            $this->_dcHostname, 
            $reqString, 
            http_build_query($paramArray)
        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIE, '_forum_session='.$this->_sessionKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $body = curl_exec($ch);
        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($rc == 200) ? $body : false;
    }

    private function _putRequest($reqString, $paramArray)
    {
        return $this->_postRequest($reqString, $paramArray, true);
    }

    private function _postRequest($reqString, $paramArray, $putMethod = false)
    {
        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s?api_key=%s&api_username=%s',
            $this->_protocol, 
            $this->_dcHostname, 
            $reqString, 
            $this->_apiKey, 
            $this->_userName
        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paramArray));
        curl_setopt($ch, CURLOPT_COOKIE, '_forum_session='.$this->_sessionKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($putMethod) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        }
        $body = curl_exec($ch);

        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($rc == 200) ? $body : false;
    }

    /**
     * changeSiteSetting
     *
     * @param string $name  name of site setting to be modified
     * @param string $value value of site setting to be modified
     *
     * @return mixed json or HTTP return code
     */

    function changeSiteSetting($name, $value)
    {
        $this->_ensureSession();
        $rc = $this->_putRequest(
            '/admin/site_settings/'.$name, 
            array('value' => $value)
        );
        return $rc;
    }

    /**
     * createUser
     *
     * @param string $name         name of new user
     * @param string $userName     username of new user
     * @param string $emailAddress email address of new user
     * @param string $password     password of new user
     *
     * @return mixed json or HTTP return code
     */

    function createUser($name, $userName, $emailAddress, $password)
    {
        $json = $this->_getRequest('/users/hp.json');
        if ($json === false) {
            return false;
        }

        $obj = json_decode($json);

        $params = array(
            'name' => $name,
            'username' => $userName,
            'email' => $emailAddress,
            'password' => $password,
            'challenge' => strrev($obj->challenge),
            'password_confirmation' => $obj->value
        );

        return $this->_postRequest('/users', $params);
    }

    function createCategory($name, $color, $textColor = '000000')
    {
        $params = array(
            'name' => $name,
            'color' => $color,
            'text_color' => $textColor
        );
        return $this->_postRequest('/categories', $params);
    }

    function createTopic($title, $bodyText, $category, $replyTo = 0) 
    {
        $params = array(
            'title' => $title,
            'raw' => $bodyText,
            'category' => $category,
            'archetype' => 'regular',
            'reply_to_post_number' => $replyTo,
        );
        return $this->_postRequest('/posts', $params);
    }

    function createPost($bodyText, $topicId)
    {
        $params = array(
            'raw' => $bodyText,
            'archetype' => 'regular',
            'topic_id' => $topicId
        );
        return $this->_postRequest('/posts', $params);
    }

}


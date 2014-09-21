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
    private $_protocol = 'http';
    private $_apiKey = null;
    private $_dcHostname = null;

    function __construct($dcHostname, $apiKey = null, $protocol='http')
    {
        $this->_dcHostname = $dcHostname;
	$this->_apiKey = $apiKey;
	$this->_protocol=$protocol;
    }

    private function _getRequest($reqString, $paramArray = null, $apiUser = 'system')
    {
        if ($paramArray == null) {
            $paramArray = array();
        }
        $paramArray['api_key'] = $this->_apiKey;
        $paramArray['api_username'] = $apiUser;
        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s?%s',
            $this->_protocol, 
            $this->_dcHostname, 
            $reqString, 
            http_build_query($paramArray)
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $body = curl_exec($ch);
        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resObj = new \stdClass();
        $resObj->http_code = $rc;
        $resObj->apiresult = json_decode($body);
        return $resObj;
    }

    private function _putRequest($reqString, $paramArray, $apiUser = 'system')
    {
        return $this->_putpostRequest($reqString, $paramArray, $apiUser, true);
    }

    private function _postRequest($reqString, $paramArray, $apiUser = 'system')
    {
        return $this->_putpostRequest($reqString, $paramArray, $apiUser, false);
    }

    private function _putpostRequest($reqString, $paramArray, $apiUser = 'system', $putMethod = false)
    {
        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s?api_key=%s&api_username=%s',
            $this->_protocol, 
            $this->_dcHostname, 
            $reqString, 
            $this->_apiKey, 
            $apiUser
        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paramArray));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($putMethod) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        }
        $body = curl_exec($ch);
        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resObj = new \stdClass();
        $resObj->http_code = $rc;
        $resObj->apiresult = json_decode($body);
        return $resObj;
    }

    /**
     * group
     *
     * @param string $groupname         name of group
     * @param string $usernames     users to add to group
     *
     * @return mixed HTTP return code and API return object
     */

    function group($groupname, $usernames = array())
    {
        $obj = $this->_getRequest("/admin/groups.json");
        if ($obj->http_code != 200) {
            return false;
        }

        foreach($obj->apiresult as $group) {
            if($group->name === $groupname) {
                $groupId = $group->id;
                break;
            }
            $groupId = false;
        }

        $params = array(
            'group' => array(
                'name' => $groupname,
                'usernames' => implode(',', $usernames)
            )
        );

        if($groupId) {
            return $this->_putRequest('/admin/groups/' . $groupId, $params);
        } else {
            return $this->_postRequest('/admin/groups', $params);
        }
    }

    /**
     * getGroups
     *
     * @return mixed HTTP return code and API return object
     */

    function getGroups()
    {
        return $this->_getRequest("/admin/groups.json");
    }

    /**
     * getGroupMembers
     *
     * @param string $group         name of group
     * @return mixed HTTP return code and API return object
     */

    function getGroupMembers($group)
    {
        return $this->_getRequest("/groups/{$group}/members.json");
    }

    /**
     * createUser
     *
     * @param string $name         name of new user
     * @param string $userName     username of new user
     * @param string $emailAddress email address of new user
     * @param string $password     password of new user
     *
     * @return mixed HTTP return code and API return object
     */

    function createUser($name, $userName, $emailAddress, $password)
    {
        $obj = $this->_getRequest('/users/hp.json');
        if ($obj->http_code != 200) {
            return false;
        }

        $params = array(
            'name' => $name,
            'username' => $userName,
            'email' => $emailAddress,
            'password' => $password,
            'challenge' => strrev($obj->apiresult->challenge),
            'password_confirmation' => $obj->apiresult->value
        );

        return $this->_postRequest('/users', $params);
    }

    /**
     * activateUser
     *
     * @param integer $userId      id of user to activate
     *
     * @return mixed HTTP return code 
     */

    function activateUser($userId)
    {
        return $this->_putRequest("/admin/users/{$userId}/activate", array());
    }

    /**
     * getUsernameByEmail
     *
     * @param string $email     email of user
     *
     * @return mixed HTTP return code and API return object
     */

    function getUsernameByEmail($email)
    {
        $users = $this->_getRequest("/admin/users/list/active.json?filter=".urlencode($email));
        foreach($users->apiresult as $user) {
            if($user->email === $email) {
                return $user->username;
            }
        }
	
        return false;
    }

     /**
     * getUserByUsername
     *
     * @param string $userName     username of user
     *
     * @return mixed HTTP return code and API return object
     */

    function getUserByUsername($userName)
    {
        return $this->_getRequest("/users/{$userName}.json");
    }

    /**
     * createCategory
     *
     * @param string $categoryName name of new category
     * @param string $color        color code of new category (six hex chars, no #)
     * @param string $textColor    optional color code of text for new category
     * @param string $userName     optional user to create category as
     *
     * @return mixed HTTP return code and API return object
     */

    function createCategory($categoryName, $color, $textColor = '000000', $userName = 'system')
    {
        $params = array(
            'name' => $categoryName,
            'color' => $color,
            'text_color' => $textColor
        );
        return $this->_postRequest('/categories', $params, $userName);
    }

    /**
     * createTopic
     *
     * @param string $topicTitle   title of topic
     * @param string $bodyText     body text of topic post
     * @param string $categoryName category to create topic in
     * @param string $userName     user to create topic as
     * @param string $replyToId    post id to reply as
     *
     * @return mixed HTTP return code and API return object
     */

    function createTopic($topicTitle, $bodyText, $categoryId, $userName, $replyToId = 0) 
    {
        $params = array(
            'title' => $topicTitle,
            'raw' => $bodyText,
            'category' => $categoryId,
            'archetype' => 'regular',
            'reply_to_post_number' => $replyToId,
        );
        return $this->_postRequest('/posts', $params, $userName);
    }

    /**
     * createPost
     *
     * NOT WORKING YET
     */

    function createPost($bodyText, $topicId, $categoryId, $userName)
    {
        $params = array(
            'raw' => $bodyText,
            'archetype' => 'regular',
            'category' => $categoryId,
            'topic_id' => $topicId
        );
        return $this->_postRequest('/posts', $params, $userName);
    }

    function inviteUser($email, $topicId, $userName = 'system')
    {
        $params = array(
            'email' => $email,
            'topic_id' => $topicId
        );
        return $this->_postRequest('/t/'.intval($topicId).'/invite.json', $params, $userName);
    }

}


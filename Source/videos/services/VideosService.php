<?php
/**
 * @link      https://dukt.net/craft/videos/
 * @copyright Copyright (c) 2015, Dukt
 * @license   https://dukt.net/craft/videos/docs/license
 */

namespace Craft;

require(CRAFT_PLUGINS_PATH.'videos/vendor/autoload.php');

class VideosService extends BaseApplicationComponent
{
    // Properties
    // =========================================================================

    private $_gateways = array();
    private $_allGateways = array();
    private $_gatewaysLoaded = false;
    private $tokens;
    private $videoGateways = array(
        'youtube' => array(
            'name' => "YouTube",
            'handle' => 'youtube',
            'oauth' => array(
                'name' => "Google",
                'handle' => 'google',
                'scopes' => array(
                    'https://www.googleapis.com/auth/userinfo.profile',
                    'https://www.googleapis.com/auth/userinfo.email',
                    'https://www.googleapis.com/auth/youtube',
                    'https://www.googleapis.com/auth/youtube.readonly'
                ),
                'params' => array(
                    'access_type' => 'offline',
                    'approval_prompt' => 'force'
                )
            )
        ),

        'vimeo' => array(
            'name' => "Vimeo",
            'handle' => 'vimeo',
            'oauth' => array(
                'name' => "Vimeo",
                'handle' => 'vimeo'
            )
        )
    );

    // Public Methods
    // =========================================================================

    public function sendRequest(Videos_RequestCriteriaModel $criteria)
    {
        $gateway = $this->getGateway($criteria->gateway);

        return $gateway->api($criteria->method, $criteria->query);
    }

    public function getGatewayOpts($handle)
    {
        return $this->videoGateways[$handle];
    }

    public function getScopes($handle)
    {
        foreach($this->videoGateways as $gateway)
        {
            if($gateway['oauth']['handle'] == $handle)
            {
                if(!empty($gateway['oauth']['scopes']))
                {
                    return $gateway['oauth']['scopes'];
                }

                break;
            }
        }

        return array();
    }

    public function getParams($handle)
    {
        foreach($this->videoGateways as $gateway)
        {
            if($gateway['oauth']['handle'] == $handle)
            {
                if(!empty($gateway['oauth']['params']))
                {
                    return $gateway['oauth']['params'];
                }

                break;
            }
        }

        return array();
    }

    /**
     * Get OAuth Token
     */
    public function getToken($handle)
    {
        if(!empty($this->tokens[$handle]))
        {
            return $this->tokens[$handle];
        }
        else
        {
            // get plugin
            $plugin = craft()->plugins->getPlugin('videos');

            // get settings
            $settings = $plugin->getSettings();

            // get tokens
            $tokens = $settings['tokens'];

            if(!empty($settings['tokens'][$handle]))
            {
                // get tokenId
                $tokenId = $tokens[$handle];

                // get token
                $token = craft()->oauth->getTokenById($tokenId);

                if($token)
                {
                    $this->tokens[$handle] = $token;
                    return $this->tokens[$handle];
                }
            }
        }
    }

    public function deleteToken($handle)
    {
        // get plugin
        $plugin = craft()->plugins->getPlugin('videos');

        // get settings
        $settings = $plugin->getSettings();

        // get tokens
        $tokens = $settings['tokens'];

        // get token

        if(!empty($tokens[$handle]))
        {
            // get tokenId
            $tokenId = $tokens[$handle];

            // get token
            $token = craft()->oauth->getTokenById($tokenId);

            if($token)
            {
                craft()->oauth->deleteToken($token);
            }

            unset($tokens[$handle]);

            // save plugin settings
            $settings['tokens'] = $tokens;
            craft()->plugins->savePluginSettings($plugin, $settings);
        }
    }

    /**
     * Save OAuth Token
     */
    public function saveToken($handle, $token)
    {
        // get plugin
        $plugin = craft()->plugins->getPlugin('videos');

        // get settings
        $settings = $plugin->getSettings();

        // get tokens
        $tokens = $settings['tokens'];

        // get token

        if(!empty($tokens[$handle]))
        {
            // get tokenId
            $tokenId = $tokens[$handle];

            // get token
            // $model = craft()->oauth->getTokenById($tokenId);
            // $token->id = $tokenId;
            $existingToken = craft()->oauth->getTokenById($tokenId);
        }


        if(!$token)
        {
            $token = new Oauth_TokenModel;
        }

        if(isset($existingToken))
        {
            $token->id = $existingToken->id;
        }

        $token->providerHandle = $handle;
        $token->pluginHandle = 'videos';


        // save token
        craft()->oauth->saveToken($token);

        // set token ID
        $tokens[$handle] = $token->id;

        // save plugin settings
        $settings['tokens'] = $tokens;
        craft()->plugins->savePluginSettings($plugin, $settings);
    }

    public function getVideoThumbnail($gateway, $id, $w = 340, $h = null)
    {
        $uri = 'videosthumbnails/'.$gateway.'/'.$id.'/';

        $uri .= $w;

        if(!$h)
        {
            // calculate hd ratio (1,280×720)
            $h = floor($w * 720 / 1280);
        }

        $uri .= '/'.$h;


        return UrlHelper::getResourceUrl($uri);
    }

    public function getVideoByUrl($videoUrl, $enableCache = true, $cacheExpiry = 3600)
    {
        $video = $this->_getVideoObjectByUrl($videoUrl, $enableCache, $cacheExpiry);

        if($video)
        {

            $attributes = (array) $video;

            $response = Videos_VideoModel::populateModel($attributes);

            // $response['thumbnail'] = $response->getThumbnail();

            return $response;
        }
    }

    public function getVideoById($gateway, $id)
    {
        $video = $this->_getVideoObjectById($gateway, $id);

        if($video)
        {

            $video = (array) $video;

            $response = Videos_VideoModel::populateModel($video);

            // $response['thumbnail'] = $response->getThumbnail();

            return $response;
        }
    }

    public function getEmbed($videoUrl, $opts)
    {
        $video = $this->_getVideoObjectByUrl($videoUrl);

        return $video->getEmbed($opts);
    }

    public function _getVideoObjectById($gatewayHandle, $id, $enableCache = true, $cacheExpiry = 3600)
    {
        if($enableCache)
        {
            $key = 'videos.video.'.$gatewayHandle.'.'.md5($id);

            $response = craft()->fileCache->get($key);

            if($response)
            {
                return $response;
            }
        }

        try
        {

            $gateways = $this->getGateways();

            foreach($gateways as $gateway)
            {
                if($gateway->getHandle() == $gatewayHandle)
                {

                    $response = $gateway->getVideo(array('id' => $id));

                    if($response)
                    {
                        if($enableCache)
                        {
                            craft()->fileCache->set($key, $response, $cacheExpiry);
                        }

                        return $response;
                    }
                }
            }

        }
        catch(\Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }

    public function _getVideoObjectByUrl($videoUrl, $enableCache = true, $cacheExpiry = 3600)
    {
        if(craft()->config->get('disableCache', 'videos') == true)
        {
            $enableCache = false;
        }

        $enableCache = false;

        if($enableCache)
        {
            $key = 'videos.video.'.md5($videoUrl);

            $response = craft()->fileCache->get($key);

            if($response)
            {
                return $response;
            }
        }

        $gateways = $this->getGateways();


        foreach($gateways as $gateway)
        {
            $params['url'] = $videoUrl;
            try
            {
                $response = $gateway->videoFromUrl($params);

                if($response)
                {
                    if($enableCache)
                    {
                        craft()->fileCache->set($key, $response, $cacheExpiry);
                    }

                    return $response;
                }

            }
            catch(\Exception $e)
            {
                // todo
                // throw new Exception($e);
            }

        }


        return false;
    }

    public function getGateways($enabledOnly = true)
    {
        $this->loadGateways();

        if($enabledOnly)
        {
            return $this->_gateways;
        }
        else
        {
            return $this->_allGateways;
        }
    }

    public function getGateway($gatewayHandle, $enabledOnly = true)
    {
        $this->loadGateways();

        if($enabledOnly)
        {
            $gateways = $this->_gateways;
        }
        else
        {
            $gateways = $this->_allGateways;
        }

        foreach($gateways as $g)
        {
            if($g->getHandle() == $gatewayHandle)
            {
                return $g;
            }
        }

        return null;
    }

    public function loadGateways()
    {

        if(!$this->_gatewaysLoaded)
        {
            $files = IOHelper::getFiles(CRAFT_PLUGINS_PATH.'videos/gateways');

            foreach($files as $file)
            {
                require_once($file);

                $gatewayName = IOHelper::getFilename($file, false);

                $nsClass = '\\Dukt\\Videos\\Gateways\\'.$gatewayName;


                // gateway

                $gateway = new $nsClass;


                // provider

                $handle = strtolower($gateway->oauthProvider);


                // token

                $token = $this->getToken($handle);

                if($token)
                {
                    $gateway->setToken($token);

                    // add to loaded gateways
                    $this->_gateways[] = $gateway;
                }


                // add to all gateways
                $this->_allGateways[] = $gateway;
            }

            $this->_gatewaysLoaded = true;
        }
    }
}

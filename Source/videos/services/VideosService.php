<?php
/**
 * @link      https://dukt.net/craft/videos/
 * @copyright Copyright (c) 2015, Dukt
 * @license   https://dukt.net/craft/videos/docs/license
 */

namespace Craft;

class VideosService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    /**
     * Explorer Nav
     */
    public function getExplorerNav()
    {
        $nav = [];

        $gateways = craft()->videos_gateways->getGateways();

        foreach ($gateways as $gateway)
        {
            $nav[] = $gateway;
        }

        return $nav;
    }

    /**
     * Get a video from its ID
     */
    public function getVideoById($gateway, $id)
    {
        $video = $this->requestVideoById($gateway, $id);

        if($video)
        {
            return $video;
        }
    }

    /**
     * Get a video from its URL
     */
    public function getVideoByUrl($videoUrl, $enableCache = true, $cacheExpiry = 3600)
    {
        $video = $this->requestVideoByUrl($videoUrl, $enableCache, $cacheExpiry);

        if($video)
        {
            return $video;
        }
    }

    /**
     * Send Request
     */
    public function sendRequest(Videos_RequestCriteriaModel $criteria)
    {
        $gateway = craft()->videos_gateways->getGateway($criteria->gateway);
        return $gateway->api($criteria->method, $criteria->query);
    }

    // Private Methods
    // =========================================================================

    /**
     * Request a video from its ID
     */
    private function requestVideoById($gatewayHandle, $id, $enableCache = true, $cacheExpiry = 3600)
    {
        if($enableCache)
        {
            $key = 'videos.video.'.$gatewayHandle.'.'.md5($id);

            $response = craft()->videos_cache->get([$key]);

            if($response)
            {
                return $response;
            }
        }

        $gateway = craft()->videos_gateways->getGateway($gatewayHandle);

        $response = $gateway->getVideo(array('id' => $id));

        if($response)
        {
            if($enableCache)
            {
                craft()->videos_cache->set([$key], $response, $cacheExpiry);
            }

            return $response;
        }
    }

    /**
     * Request a video from its URL
     */
    private function requestVideoByUrl($videoUrl, $enableCache = true, $cacheExpiry = 3600)
    {
        if(craft()->config->get('enableCache', 'videos') === false)
        {
            $enableCache = false;
        }

        if($enableCache)
        {
            $key = 'videos.video.'.md5($videoUrl);

            $response = craft()->videos_cache->get([$key]);

            if($response)
            {
                return $response;
            }
        }

        $gateways = craft()->videos_gateways->getGateways();

        foreach($gateways as $gateway)
        {
            $params['url'] = $videoUrl;

            try
            {
                $video = $gateway->getVideoByUrl($params);

                if($video)
                {
                    if($enableCache)
                    {
                        craft()->videos_cache->set([$key], $video, $cacheExpiry);
                    }

                    return $video;
                }

            }
            catch(\Exception $e)
            {
                VideosHelper::log('Couldn’t get video: '.$e->getMessage(), LogLevel::Error);
            }
        }

        return false;
    }
}

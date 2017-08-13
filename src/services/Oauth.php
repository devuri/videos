<?php
/**
 * @link      https://dukt.net/craft/videos/
 * @copyright Copyright (c) 2017, Dukt
 * @license   https://dukt.net/craft/videos/docs/license
 */

namespace dukt\videos\services;

use Craft;
use craft\helpers\UrlHelper;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Grant\RefreshToken;
use yii\base\Component;
use dukt\videos\Plugin as Videos;

/**
 * Class Oauth service.
 *
 * An instance of the Oauth service is globally accessible via [[Plugin::oauth `Videos::$plugin->getOauth()`]].
 *
 * @author Dukt <support@dukt.net>
 * @since  2.0
 */
class Oauth extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Get token from gateway handle
     *
     * @param $handle
     *
     * @return mixed
     */
    public function getToken($handle)
    {
        $plugin = Craft::$app->getPlugins()->getPlugin('videos');
        $settings = $plugin->getSettings();
        $tokens = $settings->tokens;

        $gateway = Videos::$plugin->getGateways()->getGateway($handle);

        if(!empty($tokens[$handle]) && is_array($tokens[$handle]))
        {
            return $gateway->createTokenFromData($tokens[$handle]);
        }
    }

    /**
     * Saves a token
     *
     * @param $handle
     * @param $token
     */
    public function saveToken($handle, AccessToken $token)
    {
        $handle = strtolower($handle);

        // get plugin
        $plugin = Craft::$app->getPlugins()->getPlugin('videos');

        // get settings
        $settings = $plugin->getSettings();

        // get tokens
        $tokens = $settings->tokens;

        if(!is_array($tokens))
        {
            $tokens = [];
        }

        // set token
        $tokens[$handle] = [
            'accessToken' => $token->getToken(),
            'expires' => $token->getExpires(),
            'resourceOwnerId' => $token->getResourceOwnerId(),
            'values' => $token->getValues(),
        ];

        if(!empty($token->getRefreshToken()))
        {
            $tokens[$handle]['refreshToken'] = $token->getRefreshToken();
        }

        // save plugin settings
        $settings->tokens = $tokens;

        Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->getAttributes());
    }

    /**
     * Deletes a token
     *
     * @param $handle
     */
    public function deleteToken($handle)
    {
        $handle = strtolower($handle);

        // get plugin
        $plugin = Craft::$app->getPlugins()->getPlugin('videos');

        // get settings
        $settings = $plugin->getSettings();

        // get tokens
        $tokens = $settings->tokens;

        // get token

        if(!empty($tokens[$handle]))
        {
            unset($tokens[$handle]);

            // save plugin settings
            $settings->tokens = $tokens;
            Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->getAttributes());
        }
    }

    public function getJavascriptOrigin()
    {
        return UrlHelper::baseUrl();
    }

    public function getRedirectUri()
    {
        return UrlHelper::actionUrl('videos/oauth/callback');
    }
}
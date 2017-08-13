<?php
/**
 * @link      https://dukt.net/craft/videos/
 * @copyright Copyright (c) 2017, Dukt
 * @license   https://dukt.net/craft/videos/docs/license
 */

namespace dukt\videos;

use Craft;
use craft\events\DefineComponentsEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\ResolveResourcePathEvent;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\services\Resources;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use dukt\videos\base\PluginTrait;
use dukt\videos\fields\Video as VideoField;
use dukt\videos\models\Settings;
use dukt\videos\web\twig\variables\VideosVariable;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $hasCpSettings = true;

    /**
     * @var \dukt\videos\Plugin The plugin instance.
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'videos' => \dukt\videos\services\Videos::class,
            'cache' => \dukt\videos\services\Cache::class,
            'gateways' => \dukt\videos\services\Gateways::class,
            'oauth' => \dukt\videos\services\Oauth::class,
        ]);

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, [$this, 'registerCpUrlRules']);

        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = VideoField::class;
        });

        Event::on(Resources::class, Resources::EVENT_RESOLVE_RESOURCE_PATH, function(ResolveResourcePathEvent $event) {
            if (strpos($event->uri, 'videos/') === 0) {
                $path = $this->getResourcePath($event->uri);
                $event->path = $path;

                // Prevent other event listeners from getting invoked
                $event->handled = true;
            }
        });

        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS, function(RegisterCacheOptionsEvent $event) {
            $event->options[] = [
                'key' => 'videos-caches',
                'label' => Craft::t('videos', 'Videos caches'),
                'action' => Craft::$app->path->getRuntimePath().'/videos'
            ];
        });

        Event::on(CraftVariable::class, CraftVariable::EVENT_DEFINE_COMPONENTS, function(DefineComponentsEvent $event) {
            $event->components['videos'] = VideosVariable::class;
        });
    }

    public function registerCpUrlRules(RegisterUrlRulesEvent $event)
    {
        $rules = [
            'videos/settings' => 'videos/settings/index',
            'videos/settings/<gatewayHandle:{handle}>' => 'videos/settings/gateway',
            'videos/settings/<gatewayHandle:{handle}>/oauth' => 'videos/settings/gateway-oauth',
        ];

        $event->rules = array_merge($event->rules, $rules);
    }

    /**
     * Get OAuth Providers
     */
    public function getVideosGateways()
    {
        return [
            'dukt\videos\gateways\Vimeo',
            'dukt\videos\gateways\YouTube',
        ];
    }

    /**
     * Adds support for video thumbnail resource paths.
     *
     * @param string $path
     * @return string|null
     */
    public function getResourcePath($path)
    {
        $segs = explode('/', $path);

        if($segs[0] === 'videos' && $segs[1] === 'thumbnails')
        {
            $gateway = $segs[2];
            $videoId = $segs[3];
            $size = $segs[4];

            if (!is_numeric($size) && $size !== "original")
            {
                return false;
            }

            $video = self::$plugin->getVideos()->getVideoById($gateway, $videoId);
            $url = $video->thumbnailSource;

            $basePath = Craft::$app->path->getRuntimePath().'/videos/thumbnails/';

            if (!is_dir($basePath)) {
                FileHelper::createDirectory($basePath);
            }

            $filename = pathinfo($url, PATHINFO_BASENAME);

            if(strpos($filename, '?') !== false)
            {
                $filename = substr($filename, 0, strpos($filename, '?'));
            }

            $thumbnailsFolderPath = $basePath.$gateway.'/'.$videoId.'/';

            $originalFolderPath = $thumbnailsFolderPath.'original/';
            $originalThumbnailPath = $originalFolderPath.$filename;

            $sizedThumbnailFolder = $thumbnailsFolderPath.$size.'/';
            $sizedThumbnailPath = $sizedThumbnailFolder.$filename;

            // If the photo doesn't exist at this size, create it.
            if (!file_exists($sizedThumbnailPath))
            {
                if (!file_exists($originalThumbnailPath))
                {
                    if (!is_dir($originalFolderPath)) {
                        FileHelper::createDirectory($originalFolderPath);
                    }


                    $client = new \GuzzleHttp\Client();

                    $response = $client->request('GET', $url, array(
                        'save_to' => $originalThumbnailPath
                    ));

                    if (!$response->getStatusCode() != 200)
                    {
                        return null;
                    }
                }

                if (!is_dir($sizedThumbnailFolder)) {
                    FileHelper::createDirectory($sizedThumbnailFolder);
                }

                if (FileHelper::isWritable($sizedThumbnailFolder))
                {
                    Craft::$app->images->loadImage($originalThumbnailPath, $size, $size)
                        ->resize($size)
                        ->saveAs($sizedThumbnailPath);
                }
                else
                {
                    Craft::info('Tried to write to target folder and could not: '.$sizedThumbnailFolder, __METHOD__);
                }
            }

            return $sizedThumbnailPath;
        }
    }

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    public function getSettingsResponse()
    {
        $url = UrlHelper::cpUrl('videos/settings');

        Craft::$app->controller->redirect($url);

        return '';
    }
}
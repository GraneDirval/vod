<?php

namespace App\Domain\Entity;

use CommonDataBundle\Entity\Interfaces\HasUuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Component\HttpFoundation\File\File;

class Game implements HasUuid
{
    public static $baseUrl = "/";

    /**
     * Constants used for determining the location of different resources for this entity
     */
    const RESOURCE_POSTERS = 'images/game_icons';

    /**
     * Constants used for different types of ratings which can be applied to games
     */
    const RATING_TYPE_2_STARS = 2;
    const RATING_TYPE_3_STARS = 3;
    const RATING_TYPE_4_STARS = 4;
    const RATING_TYPE_5_STARS = 5;

    /**
     * Constants used to determine the name of the ratings which are used in the admin panel
     */
    const RATING_NAME_2_STARS = 'A';
    const RATING_NAME_3_STARS = 'AA';
    const RATING_NAME_4_STARS = 'AAA-';
    const RATING_NAME_5_STARS = 'AAA+';

    const BYTES_IN_MEGABYTE = 1048576;

    /**
     * @var string
     */
    private $uuid;

    /**
     * @var string
     */
    private $title;

    /**
     * @var boolean
     */
    private $published = true;

    /**
     * @var integer
     */
    private $rating;

    /**
     * @var string
     */
    private $icon;

    /**
     * @var File
     */
    private $icon_file;

    /**
     * @var string
     */
    private $thumbnail;

    /**
     * @var File
     */
    private $thumbnail_file;

    /**
     * @var string
     */
    private $description;

    /**
     * @var Developer
     */
    private $developer;

    /**
     * @var Collection
     */
    private $images;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var ArrayCollection
     */
    protected $translations;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var Collection
     */
    private $builds;

    /**
     * @var \DateTime
     */
    private $created;

    /**
     * @var \DateTime
     */
    private $updated;

    /**
     * @var \DateTime
     */
    private $deletedAt;

    /**
     * @var Boolean
     */
    private $isBookmark = false;

    /**
     * Returns a list with all available ratings
     *
     * @param bool $flip
     * @return array
     */
    public static function getAvailableRatings($flip = false)
    {
        $tags = [
            Game::RATING_TYPE_2_STARS => Game::RATING_NAME_2_STARS,
            Game::RATING_TYPE_3_STARS => Game::RATING_NAME_3_STARS,
            Game::RATING_TYPE_4_STARS => Game::RATING_NAME_4_STARS,
            Game::RATING_TYPE_5_STARS => Game::RATING_NAME_5_STARS,
        ];

        return $flip ? array_flip($tags) : $tags;
    }

    /**
     * Game constructor.
     *
     * @param string $uuid
     *
     * @throws \Exception
     */
    public function __construct(string $uuid)
    {
        $this->uuid = $uuid;
        $this->setCreated(new \DateTime('now'));
        $this->setUpdated(new \DateTime('now'));
        $this->builds = new ArrayCollection();
        $this->translations = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getTitle() ?? '';
    }

    /**
     * @param string $uuid
     */
    public function setUuid(string $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set title
     *
     * @param string $title
     *
     * @return Game
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set published
     *
     * @param boolean $published
     *
     * @return Game
     */
    public function setPublished($published)
    {
        $this->published = $published;

        return $this;
    }

    /**
     * Get published
     *
     * @return boolean
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * Get icon
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }


    /**
     * Get icon path
     *
     * @return string
     */
    public function getIconPath()
    {
        return static::RESOURCE_POSTERS . '/' . $this->getIcon();
    }

    /**
     * Get icon file
     *
     * @return File
     */
    public function getIconFile()
    {
        return $this->icon_file;
    }

    /**
     * Set icon
     *
     * @param string $icon
     *
     * @return Game
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set icon file
     *
     * @param File $file
     * @return Game
     */
    public function setIconFile(File $file)
    {
        $this->icon_file = $file;

        return $this;
    }

    /**
     * Get thumbnail
     *
     * @return string
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * Get thumbnail file
     *
     * @return File
     */
    public function getThumbnailFile()
    {
        return $this->thumbnail_file;
    }


    /**
     * Set thumbnail
     *
     * @param string $thumbnail
     *
     * @return Game
     */
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /**
     * Set thumbnail file
     *
     * @param File $file
     * @return Game
     */
    public function setThumbnailFile(File $file)
    {
        $this->thumbnail_file = $file;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Game
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set downloads
     *
     * @param integer $downloads
     *
     * @return Game
     */
    public function setDownloads($downloads)
    {
        $this->downloads = $downloads;

        return $this;
    }

    /**
     * Get developer
     *
     * @return Developer
     */
    public function getDeveloper()
    {
        return $this->developer;
    }

    /**
     * Set developer
     *
     * @param Developer $developer
     *
     * @return Game
     */
    public function setDeveloper(Developer $developer = null)
    {
        $this->developer = $developer;

        return $this;
    }

    /**
     * Get images
     *
     * @return Collection
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * Add image
     *
     * @param GameImage $image
     *
     * @return Game
     */
    public function addImage(GameImage $image)
    {
        $this->images[] = $image;
        $image->setGame($this);

        return $this;
    }

    /**
     * @param $imagesCollection
     */
    public function setImages($imagesCollection)
    {
        $this->images = $imagesCollection;
    }

    /**
     * Remove image
     *
     * @param GameImage $image
     */
    public function removeImage(GameImage $image)
    {
        $this->images->removeElement($image);
    }

    /**
     * @param $locale
     */
    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Returns the game's curreny
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Sets the currency form the game's price
     * @param string $currency
     * @return Game
     */
    public function setCurrency(string $currency): Game
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Add build
     *
     * @param GameBuild $build
     *
     * @return Game
     */
    public function addBuild(GameBuild $build)
    {
        $this->builds[] = $build;

        $build->setGame($this);

        return $this;
    }

    /**
     * Remove build
     *
     * @param GameBuild $build
     */
    public function removeBuild(GameBuild $build)
    {
        $this->builds->removeElement($build);
    }

    /**
     * Get builds
     *
     * @return Collection
     */
    public function getBuilds()
    {
        return $this->builds;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     *
     * @return Game
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set updated
     *
     * @param \DateTime $updated
     *
     * @return Game
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Get updated
     *
     * @return \DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Set deletedAt
     *
     * @param \DateTime $deletedAt
     *
     * @return Game
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Get deletedAt
     *
     * @return \DateTime
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * Set rating
     *
     * @param integer $rating
     *
     * @return Game
     */
    public function setRating($rating)
    {
        $this->rating = $rating;

        return $this;
    }

    /**
     * Get rating
     *
     * @return integer
     */
    public function getRating()
    {
        return $this->rating;
    }

    public function getApkSize()
    {
        /** @var GameBuild[] $builds */
        $builds = $this->getBuilds()->toArray();
        if (count($builds) == 0) {
            return "apk not found. 0 ";
        };
        return number_format($builds[0]->getApkSize() / Game::BYTES_IN_MEGABYTE, 1, ',', ' ');
    }

    //TODO: make it
    public function getSlug()
    {
        return $this->getName();
        /** @var PersistentCollection $trem */
        // $trem = $this->getTranslations('name', 'en');
        // $t = $trem->get(0);
        // $ret = strtolower($t ? $t->getContent() : $this->getName());
        // $ret = preg_replace("/[^a-z0-9_']/i", "-", $ret);
        // $ret = str_replace(['"', "'", '--'], ['-', '-', '-'], $ret);
        // return $ret;
    }


    /**
     * custom flag, non db related
     * @var bool
     */
    private $_isDownloadEnabled = false;
    private $_isDownloadDisabled = false;
    private $_isRedownloadableEnabled = false;
    private $_isRedownloadableDisabled = false;
    private $_isAllowTrial = false;

    public function isDownloadable()
    {
        return $this->_isDownloadEnabled || $this->_isRedownloadableEnabled;
    }

    public function isDownloadEnabled()
    {
        return $this->_isDownloadEnabled;
    }

    public function isDownloadDisabled()
    {
        return $this->_isDownloadDisabled;
    }

    public function isRedownloadableEnabled()
    {
        /*  var_dump($this->_isRedownloadableEnabled);*/
        return $this->_isRedownloadableEnabled;
    }

    public function isRedownloadableDisabled()
    {
        return $this->_isRedownloadableDisabled;
    }

    public function isAllowTrial()
    {
        return $this->_isAllowTrial;
    }

    public function isDownloadableDebug()
    {
        echo '<pre>' . $this->uuid
            . ' de:' . ($this->_isDownloadEnabled ? 1 : 0)
            . ' dd:' . ($this->_isDownloadDisabled ? 1 : 0)
            . ' re:' . ($this->_isRedownloadableEnabled ? 1 : 0)
            . ' rd:' . ($this->_isRedownloadableDisabled ? 1 : 0)
            . ' at:' . ($this->_isAllowTrial ? 1 : 0)
            . '</pre>';
    }

    public function setIsDownloadable($isDownloadEnabled,
        $isDownloadDisabled,
        $isRedownloadableEnabled,
        $isRedownloadableDisabled,
        $isAllowTrial)
    {
        $this->_isDownloadEnabled = $isDownloadEnabled;
        $this->_isDownloadDisabled = $isDownloadDisabled;
        $this->_isRedownloadableEnabled = $isRedownloadableEnabled;
        $this->_isRedownloadableDisabled = $isRedownloadableDisabled;
        $this->_isAllowTrial = $isAllowTrial;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->getTitle();
    }

    /**
     * @return string
     */
    public function getPublisher()
    {
        return $this->getDeveloper()->getName();
    }

    /**
     * Set isBookmark
     *
     * @param boolean $isBookmark
     *
     * @return Game
     */
    public function setIsBookmark($isBookmark)
    {
        $this->isBookmark = $isBookmark;

        return $this;
    }

    /**
     * Get isBookmark
     *
     * @return boolean
     */
    public function getIsBookmark()
    {
        return $this->isBookmark;
    }

    /**
     * @return string
     * This is how we generate the token to identify traffic intended for aff campaigns.
     * /?cmpId=token
     * We don't need to store the computed token anywhere inside the DB to identify a specific campaign,
     * because we get its ID it by decoding the token. This applies only when there is no enforced ID from affiliate.
     * This method is called only by configureListFields() inside AppBundle\Admin\CampaignAdmin
     *
     *
     * The parameter names are hardcoded, and should be read from app/config/parameters.yml
     */
    public function getPageUrl()
    {
        return "http://" . $_SERVER['SERVER_NAME'] . "/trial?bt=" . base64_encode(json_encode(array('name' => $this->getName(), 'id' => $this->getUuid())));
    }
}
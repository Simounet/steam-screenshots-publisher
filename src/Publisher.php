<?php

namespace Simounet\SteamScreenshotsPublisher;

class Publisher {
    public $publishPath = '';
    public $steamScreensPath = '';
    protected $apiGamesInfosFile = 'apigamesinfos.json';
    protected $steamApiAppListUrl = 'http://api.steampowered.com/ISteamApps/GetAppList/v2';

    public function __construct($steamScreensPath, $publishPath) {
        $this->steamScreensPath = $steamScreensPath;
        $this->publishPath = $publishPath;
    }

    public function publish() {
        try {
            $appList = $this->getAppList();
            $gamesIdToName = $this->getGamesIdToName($appList);
            $this->createPathIfNotExists($this->publishPath);

            foreach (new \DirectoryIterator($this->steamScreensPath) as $file) {
                if( $file->isDot() ) continue;
                $gameId = $file->getFilename();
                $gameName = $this->getGameName($gamesIdToName, $gameId);
                $gameNameClean = $this->sanitize($gameName);
                $publishGamePath = $this->publishPath . $gameNameClean . '/';
                $this->createPathIfNotExists($publishGamePath);
                $steamGameScreensPath = $this->steamScreensPath . $gameId . '/screenshots/';
                foreach (new \DirectoryIterator($steamGameScreensPath) as $screen) {
                    if( $screen->isDot() ) continue;
                    $screenFile = $screen->getFilename();
                    if( $screenFile === 'thumbnails' ) continue;
                    $publishGameScreenPath = $publishGamePath . $screenFile;
                    $steamGameScreenPath = $steamGameScreensPath . $screenFile;
                    $this->copyScreenshot($steamGameScreenPath, $publishGameScreenPath);
                }
            }
        } catch( \Exception $e ) {
            echo $e->getMessage();
        }
    }

    protected function getGameName($gamesIdToName, $gameId, $try = 0) {
        if(!isset($gamesIdToName[$gameId])) {
            if($try === 0) {
                $appList = $this->getAppListFromApi();
                $gamesIdToName = $this->getGamesIdToName($appList);
                $this->getGameName($gamesIdToName, $gameId, ++$try);
            } else {
                throw new \Exception("Game id $gameId unknown in the Steam API.\n");
            }
        }
        return $gamesIdToName[$gameId];
    }

    protected function getAppListFromApi() {
        $apiGamesInfos = file_get_contents($this->steamApiAppListUrl);
        $appList = $this->getAppListObject($apiGamesInfos);
        if( ! $appList ) {
            throw new \Exception("No games infos found into the Steam API. Try again later.\n");
        }
        file_put_contents($this->apiGamesInfosFile, $apiGamesInfos);
        return $appList;
    }

    protected function getAppList() {
        if(!file_exists($this->apiGamesInfosFile)) {
            $appList = $this->getAppListFromApi();
        } else {
            $apiGamesInfos = file_get_contents($this->apiGamesInfosFile);
            $appList = $this->getAppListObject($apiGamesInfos);
        }
        return $appList;
    }

    protected function getAppListObject($apiGamesInfos) {
        $apiGamesInfosObject = json_decode($apiGamesInfos);
        if(! isset($apiGamesInfosObject->applist->apps) || count($apiGamesInfosObject->applist->apps) === 0) {
            return false;
        }
        return $apiGamesInfosObject->applist->apps;
    }

    protected function getGamesIdToName($appList) {
        $idToName = [];
        foreach($appList as $game) {
            $idToName[$game->appid] = $game->name;
        }
        return $idToName;
    }

    protected function sanitize($string) {
        $string = strtolower($string);
        $string = str_replace(' ', '-', $string);
        $string = str_replace('&', 'and', $string);
        $string = preg_replace('/[^a-z0-9\-]/', '', $string);
        return preg_replace('/-+/', '-', $string);
    }

    protected function createPathIfNotExists($path) {
        if(!file_exists($path)) {
            mkdir($path);
        }
    }

    protected function copyScreenshot($steamGameScreenPath, $publishGameScreenPath) {
        if(!file_exists($publishGameScreenPath)) {
            copy($steamGameScreenPath, $publishGameScreenPath);
        }
    }
}

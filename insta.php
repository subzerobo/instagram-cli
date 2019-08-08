#!/usr/local/bin/php

<?php
require __DIR__ . '/vendor/autoload.php';

use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

class IGCLI extends CLI
{
    /**
     * Register options and arguments
     *
     * @param Options $options
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:45
     */
    protected function setup(Options $options)
    {
        $options->setHelp('Instagram Data Scrapper By Ali Kaviani');
        $options->registerOption('version', 'print version', 'v');
        $options->registerOption('user', 'Username of Instagram', 'u', true);
        $options->registerOption('pass', 'Password of Instagram', 'p', true);
        $options->registerOption('total', 'Maximum Item Download', 't', true);

        $options->registerCommand("uinfo", "prints username data");
        $options->registerOption('username', 'Username of Target Account', 'n', true, 'uinfo');

        $options->registerCommand("ufeed", "Store all feed photos/videos of a user id");
        $options->registerOption('username', 'Username of Target Account', 'n', true, 'ufeed');


        $options->registerCommand("hfeed", "Store all feed photos/videos of a hashtag");
        $options->registerOption('hashtag', 'Hashtag to Store', 'h', true, 'hfeed');

        $options->registerCommand("utest", "Test first page data of a user id feed");
        $options->registerOption('username', 'Username of Target Account', 'n', true, 'utest');


    }

    /**
     * Main Function of CLI Class
     *
     * @param Options $options
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:45
     */
    protected function main(Options $options)
    {
        switch ($options->getCmd()) {
            case 'uinfo':
                $ig = $this->getAuthenticatedIG($options);
                $this->printUserInfo($options, $ig);
                break;
            case 'ufeed':
                $ig = $this->getAuthenticatedIG($options);
                $this->storeUserFeed($options, $ig);
                break;
            case "hfeed":
                $ig = $this->getAuthenticatedIG($options);
                $this->storeHashTagFeed($options, $ig);
                break;
            case 'utest':
                $ig = $this->getAuthenticatedIG($options);
                $this->testUserFeed($options, $ig);
                break;
            default:
                if ($options->getOpt('version')) {
                    $this->info('1.0.0');
                } else {
                    $this->error('No known command was called, we show the default help instead:');
                    echo $options->help();
                }
        }
    }

    /**
     * Authenticate IG Class and Returns it
     *
     * @param Options $options
     *
     * @return \InstagramAPI\Instagram
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:05
     */
    public function getAuthenticatedIG(Options $options)
    {
        $ig = new \InstagramAPI\Instagram();
        $username = $options->getOpt('user');
        $password = $options->getOpt('pass');
        if ($username == "" || $password == "") {
            $this->error("Username or Password is not provided!, please use -u and -p option to provide username and password");
            exit;
        }
        $ig->login($username, $password);
        $this->info('Login Successful !');
        return $ig;
    }

    /**
     * Prints the Userid of Specific Username
     *
     * @param Options                 $options
     * @param \InstagramAPI\Instagram $ig
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:12
     */
    public function printUserInfo(Options $options, \InstagramAPI\Instagram $ig){
        $username = $options->getOpt("username");
        if ($username == "") {
            $this->error("Username is not provided!, please use --username or -n option to provide username");
            exit;
        }
        $data = $this->getUserID($username, $ig);
        $this->info("UserId for Username is :" . $data);
    }

    /**
     * Returns UserId of a Username
     *
     * @param string                  $username
     * @param \InstagramAPI\Instagram $ig
     *
     * @return string
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:35
     */
    private function getUserID(string $username, \InstagramAPI\Instagram $ig): string{
        return $data = $ig->people->getUserIdForName($username);
    }

    /**
     * Stores all of user feed to local drive
     *
     * @param Options                 $options
     * @param \InstagramAPI\Instagram $ig
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:35
     */
    public function storeUserFeed(Options $options, \InstagramAPI\Instagram $ig)
    {
        $username = $options->getOpt("username");
        $max = $options->getOpt("total");
        if ($username == "") {
            $this->error("Username is not provided!, please use --username or -n option to provide username");
            exit;
        }
        $userId = $this->getUserID($username, $ig);
        $folderName = "photos/users/" . $username;
        $maxId = null;
        if (!is_dir($folderName)) {
            mkdir($folderName);
        }
        $n = 0;
        do {
            $response = $ig->timeline->getUserFeed($userId, $maxId);
            foreach ($response->getItems() as $item) {
                if ($max > 0 && $n > $max) {
                    $this->success("Max Reached out ! Job Finished and Total of " . $n . "Photos and Captions Stored Successfully.");
                    exit;
                }
                if ($item->getMediaType() == 1) {
                    // Photo
                    if ($item->getImageVersions2()) {
                        $imgPath = $item->getImageVersions2()->getCandidates()[0]->getUrl();
                        $filename = $item->getId();
                        $content = $item->getCaption()->getText();
                        $this->info(sprintf("#%d - Image ID: %s", $n, $filename));
                        $this->saveImage($imgPath, $folderName, $filename);
                        $this->saveText($content, $folderName, $filename);
                        $n++;
                    } else {
                        $this->warning("Error getting ImageVersion2");
                    }
                } else {
                    // Video
                    if ($item->getVideoVersions()) {
                        $videoPath = $item->getVideoVersions()[0]->getUrl();
                        $filename = $item->getId();
                        $content = $item->getCaption()->getText();
                        $this->info(sprintf("#%d - Video ID: %s", $n, $filename));
                        $this->saveVideo($videoPath, $folderName, $filename);
                        $this->saveText($content, $folderName, $filename);
                        $n++;
                    } else {
                        $this->warning("Error getting VideoVersion");
                    }
                }
            }
            $maxId = $response->getNextMaxId();
            $this->notice("Sleeping 5 Seconds...");
            sleep(5);
        } while ($maxId !== null);
        $this->success("Job Finished and Total of " . $n . "Photos and Captions Stored Successfully.");
    }

    /**
     * Stores all of feed related to an hashtag
     *
     * @param Options                 $options
     * @param \InstagramAPI\Instagram $ig
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 23:25
     */
    public function storeHashTagFeed(Options $options, \InstagramAPI\Instagram $ig)
    {
        $hashtag = $options->getOpt("hashtag");
        $max = $options->getOpt("total");
        if ($hashtag == "") {
            $this->error("HashTag is not provided!, please use --hashtag or -n option to provide hashtag");
            exit;
        }
        $folderName = "photos/hashtags/" . $hashtag;
        $maxId = null;
        if (!is_dir($folderName)) {
            mkdir($folderName);
        }
        $n = 0;
        $rankToken = \InstagramAPI\Signatures::generateUUID();
        do {
            $response = $ig->hashtag->getFeed($hashtag, $rankToken, $maxId);
            foreach ($response->getItems() as $item) {
                if ($max > 0 && $n > $max) {
                    $this->success("Max Reached out ! Job Finished and Total of " . $n . "Photos and Captions Stored Successfully.");
                    exit;
                }
                if ($item->getMediaType() == 1) {
                    // Photo
                    if ($item->getImageVersions2()) {
                        $imgPath = $item->getImageVersions2()->getCandidates()[0]->getUrl();
                        $filename = $item->getId();
                        $content = $item->getCaption()->getText();
                        $this->info(sprintf("#%d - Image ID: %s", $n, $filename));
                        $this->saveImage($imgPath, $folderName, $filename);
                        $this->saveText($content, $folderName, $filename);
                        $n++;
                    } else {
                        $this->warning("Error getting ImageVersion2");
                    }
                } else {
                    // Video
                    if ($item->getVideoVersions()) {
                        $videoPath = $item->getVideoVersions()[0]->getUrl();
                        $filename = $item->getId();
                        $content = $item->getCaption()->getText();
                        $this->info(sprintf("#%d - Video ID: %s", $n, $filename));
                        $this->saveVideo($videoPath, $folderName, $filename);
                        $this->saveText($content, $folderName, $filename);
                        $n++;
                    } else {
                        $this->warning("Error getting VideoVersion");
                    }
                }
            }
            $maxId = $response->getNextMaxId();
            $this->notice("Sleeping 5 Seconds...");
            sleep(5);
        } while ($maxId !== null);

    }

    /**
     * Test first page of user feed
     *
     * @param Options                 $options
     * @param \InstagramAPI\Instagram $ig
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:35
     */
    public function testUserFeed(Options $options, \InstagramAPI\Instagram $ig)
    {
        $username = $options->getOpt("username");
        if ($username == "") {
            $this->error("Username is not provided!, please use --username or -n option to provide username");
            exit;
        }
        $userId = $this->getUserID($username, $ig);
        $maxId = null;
        $response = $ig->timeline->getUserFeed($userId, $maxId);
        $this->info($response->getItems()[0]->getImageVersions2()->getCandidates()[0]->getUrl());
        $this->info($response->getItems()[0]->getVideoVersions()[0]->getUrl());
        $data = $response->asJson();
        file_put_contents("./test.json", $data);
        $this->success("Job Finished Successfully.");
    }

    /**
     * Save Image from url to file
     *
     * @param $img_path
     * @param $folder
     * @param $filename
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:35
     */
    public function saveImage($img_path, $folder, $filename)
    {
        if (!file_exists("./$folder/$filename.jpg")) {
            $image = file_get_contents($img_path);
            file_put_contents("./$folder/$filename.jpg", $image);
        }
    }

    /**
     * Save Image from url to file
     *
     * @param $video_path
     * @param $folder
     * @param $filename
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:35
     */
    public function saveVideo($video_path, $folder, $filename)
    {
        if (!file_exists("./$folder/$filename.mp4")) {
            $image = file_get_contents($video_path);
            file_put_contents("./$folder/$filename.mp4", $image);
        }
    }

    /**
     * Save Caption to text File
     *
     * @param $text
     * @param $folder
     * @param $filename
     *
     * @author alikaviani <a.kaviani@sabavision.ir>
     * @since  2019-08-08 12:35
     */
    public function saveText($text, $folder, $filename)
    {
        if (!file_exists("./$folder/$filename.txt")) {
            file_put_contents("./$folder/$filename.txt", $text);
        }
    }
}

// execute it
$cli = new IGCLI();
$cli->run();

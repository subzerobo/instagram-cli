# Instagram CLI App

## Setup Guide

1. Clone the Project using `git clone git@github.com:subzerobo/instagram-cli.git`
2. Install Composer Dependencies using `composer install`
3. Make insta.php file executable using `chmod +x insta.php`
4. You can optionally add symlink to insta.php using 
`ln -s -f insta.php insta`
5. Run the Code `$ ./insta.php` it will display the command help

## Commands
### Get Help

`$ ./insta.php --help`

### Get UserId of an Instagram Username
```
$ ./insta.php -u your_instagram_username -p your_instagram_password uinfo -n davidbeckham

ℹ Login Successful !
ℹ UserId for Username is :186904952
```

### Get All Photos and Videos of Sample Users
```
$ ./insta.php -u your_instagram_username -p your_instagram_password --total=40 ufeed -n davidbeckham
```



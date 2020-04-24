## Docker Build

Before running `./build.sh`, setup a `.env` file in your root directory which contains
access to the Magento repo. You  will need to ensure that access keys are generated at
https://marketplace.magento.com/customer/accessKeys/ and populate your local `.env` file
with the correct credentials.

_Please note that the `/root/.composer/auth.json` file generated in the build is removed
after creating the project so it does not live in any pre-buit container._

The container also uses NGrok for postback testing with the Paylink service. The container
bootup script will require this to be supplied i.e. `docker container run --rm -it --env-file .env citypay/magento2:latest
` or in a compose file such as `env_file: .env`

Example .env file:
```
MAGENTO_REPO_USERNAME=0a01...
MAGENTO_REPO_PASSWORD=f06c...
NGROK_AUTHTOKEN=...
```

### Testing Build

To run the file execute in the base directory as

` docker container run --rm -it -p80:80 --env-file .env citypay/magento2:latest` or similar

* will autoremove once complete
* be interactive
* map port 80 to 80 on the local machine
* establish the environment file for runtime
* run the default command of `cron && /container-startup.sh`

The testing build is used to install a particular version of the github paylink version
and magento version in Docker.

The build runs the script `files/container-sgtartup.sh` which 

0. Setup NGROK_AUTHTOKEN and obtain the NGROK_URL
0. Starts apache in the background
0. Initialises the `/root/.composer/auth.json` file with your Magento REPO details in .env
0. Boots up `mysqld_safe` in the background (_note logs to /var/log/mysql/error.log_)
0. initialises magento with the plugin as `setup:upgrade, cache:flush, cache:clean, setup:di:compile`
0. starts a bash shell 

## Notes on Setting up Magento for the first time

This is performed in the Dockerfile build process on setup so no need to run on boot.

I found that there was no entry for the admin session lifetime resulting in an error "Your current session has been expired"
see a related [Stack Exchange discussion](https://magento.stackexchange.com/questions/209710/magento-2-admin-page-error-your-current-session-has-been-expired)

```sql
 INSERT INTO `core_config_data` (`scope`, `scope_id`, `path`, `value`)
    VALUES ('default', 0, 'admin/security/session_lifetime', '86400');
```
```shell
php bin/magento cache:clean
```


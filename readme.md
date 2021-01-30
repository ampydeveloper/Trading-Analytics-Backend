## Slabstox

### Demo Credentials

**User:** admin@admin.com  
**Password:** secret

### Introduction

Coming Soon

### setup
1. composer install
2. npm install
3. copy .env.example file to .env
4. update database credentials and E-mail credentials and other required details in .env file 
5. php artisan key:generate
6. php artisan migrate
7. php artisan db:seed
8. php artisan jwt:secret
9. npm run prod


### commands

-php artisan command:getEbayItemsForCardsCron

    ** [This Command will init the process of fecthing cards details from database and hit the ebay api for fetching items for related keyword and store thier data in to the database]

-php artisan command:CardsDataComplieCron

    ** [This Command will init the process of compling data from fecthing from ebay and save required data on table]

-php artisan command:CompareEbayImagesCron

    ** [This Command will init the process of comparing ebay item image with card image given by client and delete those ebay items which not match to the given percentage]

-php artisan command:EbayListingEndingAtComplieCron

    ** [This Command will init the process of data and findout time left of listing]

-php artisan command:CalculateUserRankCron

    ** [This Command will init the process of data and calculate user protfolio rank]

-php artisan command:GetItemAffiliateWebUrlCron

    ** [This Command will init the process of data and get itemAffiliateWebUrl ]


## Note
1. All data process under cron jobs will process through a batch for that we have to listen queue for we have a command i.e.
2. -php artisan queue:work
3. Else we can set up Supervisor which can be setup through a given link.
4. -https://laravel.com/docs/7.x/queues#supervisor-configuration



### License

MIT: [http://anthony.mit-license.org](http://anthony.mit-license.org)

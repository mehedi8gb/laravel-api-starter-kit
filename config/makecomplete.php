<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Make Complete Command Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can define which components you want to generate when running
    | the `make:complete` command. You can enable or disable specific parts
    | of the command according to your needs.
    |
    */

    'model' => true,       // Enable or disable Model creation
    'migration' => true,   // Enable or disable Migration creation
    'factory' => true,     // Enable or disable Factory creation
    'seeder' => true,      // Enable or disable Seeder creation
    'resource' => true,    // Enable or disable Resource creation
    'controller' => true,  // Enable or disable Controller creation

];

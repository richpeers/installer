# installer

Enables installing old releases of laravel.

Run ```laravel new blog --5.3``` and it will use composer rather than the desfault, effectively running ```composer create-project laravel/laravel blog "5.3.x"`

Works exactly the same as the laravel/installer except for old versions - 5.3, 5.2 and 5.1.

```laravel new blog``` uses the same methods as origin laravel/laravel for master latest, same with ```laravel new blog --dev``` for the dev branch of laravel 

## own notes to pull this down

1.) ```composer global require laravel/installer``` as normal

2.) run ```composer global info``` to find location of local global composer.json

3.) edit your local composer.json :

Before (Using official pakagist laravel/laravel repo)
```
{
    "require":
    {
        "laravel/installer": "dev-master"
    }
}
```
After (Overriding with this fork)
```
{
    "repositories":
    [
            {
                "type": "vcs",
                "url": "https://github.com/richpeers/installer"
            }
    ],
    "require":
    {
        "laravel/installer": "dev-master"
    }
}
```
4. run ```composr global update```


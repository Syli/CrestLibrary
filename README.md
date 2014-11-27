== Crest Library == 
A basic (for now) library for interacting with Crest, from EveOnline.

Requires an application to be created on the developers site before it can work


Right now, it caches region and item data in memory. This is somewhat less than efficient for single calls. Long term, I'll be shoving it into a database.




Trying it out:
get test.php, setup.php and composer.json from the test/ directory. you don't need the rest, as composer will take care of that.

fill in your details into setup.php. I can't currently help you get a renewal key. You need to auth against the sso, with the publicData Scope

If you don't have composer installed:
  # Install Composer
  curl -sS https://getcomposer.org/installer | php


run:
  composer install


then, if you run test.php, you'll get the price data for Tritanium, in the Forge, on Sisi

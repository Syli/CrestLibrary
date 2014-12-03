Crest Library
=============

A basic (for now) library for interacting with Crest, from EveOnline.

Requires an application to be created on the developers site before it can work


This is designed to work with Stash as a caching mechanism. If you don't predefine a cache pool to hand to it, it creates an ephemeral one (memory only). 

Cache timers are obeyed for all resources (well, kinda. Things get grabbed at the beginning of a call, and last that long). 

More public functions:

getEndpoints()
==============

Returns a list of endpoints, by name

getEndpoint(url, array of parameters, content type)
===================================================

Returns a data structure of the endpoint. (caches it by url, parameters and content type)

Time to live defaults to 200.


walkEndpoint(url, caching key, Collection index,index to be returned,array of parameters, content-type, max pages to walk, default ttl)
=======================================================================================================================================

Takes a url, and walks through all the next pages. Builds up an array to return.

Caching key is used with some other parameters to create a unique index for the request. So walking two regions wouldn't give you the same data.

Collection index: Which array to walk through in the result set. Often "items"

Index to be returned: How to index the array that comes back. "href" would use the href as a key. '' makes it a purely numeric key.

array of parameters: In case you need to hand in some parameters.

Content type: the content type to accept.

Max pages: Defaults to walking an infinite number of pages.

ttl: how long to keep the data for (as it doesn't bubble up from getEndpoints. I may change this. likely with another optional parameter on getEndpoint, matching the last request)





Trying it out:
==============
get test.php, setup.php and composer.json from the test/ directory. you don't need the rest, as composer will take care of that.

fill in your details into setup.php. I can't currently help you get a renewal key. You need to auth against the sso, with the publicData Scope

If you don't have composer installed:

    curl -sS https://getcomposer.org/installer | php


run:
    composer install


then, if you run test.php, you'll get the price data for Tritanium, in the Forge, on Sisi

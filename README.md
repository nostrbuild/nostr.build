# nostr.build

This repository contains source code for the https://nostr.build media hosting platform.
This platform is currently hosted on AWS servers and used to provide media hosting to the nostr social media network and related applications, but can easily be hosted on any other server and used to host media for any other service or platform.


## How to use nostr.build

nostr.build is a free to use media hosting platform that allows users to upload images, gifs, videos and audio to nostr.build servers, and returns a link that can be used to share the uploaded media. 

It is easy to use, simply go to https://nostr.build using your favorise browser (ex. Chrome, Safari, Brave), select the media you want to upload by either drag-and-dropping it on the website, or the 'Select Media' button, and nostr.build will return a link that can be used to add to nostr notes, or anywhere you want to share the image.

<img src="https://nostr.build/i/6154824466ae933fd71ef422d5316bc6bed7a6d8bc8667ae2e4492f1a063346f.jpg"  height="400">        <img src="https://nostr.build/i/4d2dccdeadc168d277b755d863a53f43b52e59cae65ec3896baab42df433ecb8.jpg"  height="400">

Other free services include:
- Media uploads to 15MB
- User profile pics cropped to the correct size for nostr and other social media platforms
- EXIF (metadata) striping from images
- Curated list of nostr related memes and content from over 40 nostr Creators
For the free, public version of nostr.build all use and media uploaded has to be legal and align to the nostr.build Terms of Service (https://nostr.build/tos). If used on another server using a different name, media and content guidelines can align to that hosting platform and owner

nostr.build provides an account system for users that want to have fewer limits to the media they are uploading and a place to store their media. Currently https://nostr.build charges a small fee for these accounts to cover server costs, but someone using the code for their own project can use the account system however they want.


## API

nostr.build provides a free API to publically facing nostr or bitcoin projects that offer their service for free. Please contact either nostr.build or fishcake if interested in using the API.


## Customization Considerations 

If installing this code on your own platform, the below are some examples of things that can be easily customized for a different experience.
- Change upload limit from 15MB to a larger or smaller value
- Add or remove certain media options. Maybe make this 'image only' or 'video only'
- Change Account options to align to your platform
- Change the html look and feel of the website
- Change where media is stored. Currently https://nostr.build stores all media on the AWS S3. This can be changed.

## Contributions and Contacts

Feel free to contact either nostr.build or fishcake for any related dev work on https://nostr.build, or questions.
Also please feel free to contribute directly to this code, community improvements are encouraged!

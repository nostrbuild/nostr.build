*****    Instructions for nostr.build simple API  ******


** Always work with nostr.build on implimenting an API. There may be specific requirements based on your application.
Contact: @npub1nxy4qpqnld6kmpphjykvx2lqwvxmuxluddwjamm4nc29ds3elyzsm5avr7

** All content uploaded must align to the nostr.build ToS: https://nostr.build/tos/

** Supports up to 10GB; jpg, png, gif, mov, mp4

____________________________________________________________________________
Free Uploads - Connecting to the API

    1) Contact https://nostr.build/ for a custom upload link - see bottom of the page for dev contact info

    2) You should receive a file such as "upload.php"

    3) Send a test file submitting the form data noted below to: https://nostr.build/api/upload/upload.php

    4) Form data to use in your test file - see above for submission URL

    <form action="upload.php" method="post" enctype="multipart/form-data">
    <input type="file" name="fileToUpload" id="fileToUpload" >
    <input type="submit" value="Upload" name="submit">  


____________________________________________________________________________
Creators Page - Curated Content Button

    1) When the user creates a note, they now have an additional button "C" which creates a pop-up of the main Creators page (https://nostr.build/creators/). The user chooses a category to view all the content within that category. From inside the category, the user selects the content for their note, and links to those files are added to their note. Number of images and/or links to content that display in the note depend upon the client of the viewer of the note. 

    2) Design overview: https://docs.google.com/presentation/d/13sztFuc3lHebRhSFmB6z4fy9Wph3pv2wslSCIwPL4HQ/edit?usp=sharing

    3) All creators JSON API: https://nostr.build/api/creators/

    4) Once selected, Creators/category images appear at this URL. Change npub to be user-specific: https://nostr.build/api/creators/?user=npub1cj8znuztfqkvq89pl8hceph0svvvqk0qay6nydgk9uyq7fhpfsgsqwrz4u

*****    Instructions for nostr.build simple API  ******


** Always work with nostr.build on implimenting an API. There may be specific requirements based on your application.
@npub1nxy4qpqnld6kmpphjykvx2lqwvxmuxluddwjamm4nc29ds3elyzsm5avr7

** All content uploaded must align to the nostr.build ToS: https://nostr.build/tos/

** Supports uo to 10GB; jpg, png, gif, mov, mp4

____________________________________________________________________________
Free Uploads - Connecting to the API

    1) Contact nostr.build for a cuatom upload link.
    2) You should receive something like 'upload.php'
    2) Send a test file submitting the below form data to: https://nostr.build/api/upload/upload.php

    3) Use below Form data:
    <form action="upload.php" method="post" enctype="multipart/form-data">
    <input type="file" name="fileToUpload" id="fileToUpload" >
    <input type="submit" value="Upload" name="submit">  


____________________________________________________________________________
Creators Page - Curated Content Button

    1) When the user goes to create a note, they would now have an additional button 'C' which will create a pop-up of the main Creatros page(https://nostr.build/creators/). They could then choose a category, and view all the content in that category. From there they can select which content they want to add to the note, and it's added.
    2) Design overview can be found here: https://docs.google.com/presentation/d/13sztFuc3lHebRhSFmB6z4fy9Wph3pv2wslSCIwPL4HQ/edit?usp=sharing

    3) All creators JSON API Here: https://nostr.build/api/creators/
    4) Oncew selected, Creatrs / category images Here (change npub): https://nostr.build/api/creators/?user=npub1cj8znuztfqkvq89pl8hceph0svvvqk0qay6nydgk9uyq7fhpfsgsqwrz4u

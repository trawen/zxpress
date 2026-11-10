```text
 ,_,                                                        __
 (o,o) -- -------------------------------------------- - -__/  \
{{ _ }} Don't ignore your hardware by LaesQ              /  \__/
- "-" ------------------------------------------------ - \__/  \
This  is  a plea to all Speccy enthusiasts with hardware    \__/
collections. Don't leave your hardware gathering dust! Get it out, dust it off and USE IT! Where has this come from? Let me tell you a story...
```

I recently saw a request for someone wanting an MB02+ disk interface on a Speccy related Telegram group. It was for some crazy idea involving a disk interface that could have four disk drives hooked up to a Speccy at once. The MB02+ is one such interface and has a number of other features like a DMA chip to help speed things up. I know I had at least three MB02+ interfaces (greedy, I know, but one isn't mine so it's fine, right?), so I went hunting them down in my vast Speccy collection. I figured I could possibly sell one of them and it could go on to actually be used and have a second life, rather than just gathering dust.

What did I find? I found two of the interfaces (Ingo Truppel versions) but I was missing my original 8BC version purchased many moons ago! Regardless I set about testing the MB02+ interfaces I'd found. They both powered on and worked fine but the floppy disks were a bit hit and miss. Some worked fine first time, others needed the fluff and dust blown out of them. In the end I only had three floppy disks that wouldn't work at all.

Well, I say the MB02s worked fine, the RTC batteries in both were flat as pancakes but CR2032 batteries are common in my house so that was easily sorted!

So now what about my 8BC version of the MB02+? After some thinking I remembered I'd lent it to Dylan Smith, he of the Spectranet fame! For those of you who don't know what a Spectranet is, where have you been all of this time? It's a hardware add-on that basically allows you to connect your Speccy to the internet via a normal RJ45 cable using modern network hardware that your PC would use. It's magic stuff. I lent Dylan my MB02+ so he could test it for hardware compatibility with his Spectranet interface. Then I completely forgot he had it!

I also remembered a long time ago that I archived all my MB02 floppy disks into MBD images. If I could find the files I'd definitely have images of the three disks that didn't work. I managed to find them on one of my many backup drives - who hasn't got more than five old USB drives full of old crap they backed up and put to one side never to be used again? I thought it'd be good to use floppy images rather real floppy disks and drives that are getting unreliable. I then remembered I had an HxC Floppy Emulator attached to my Amiga A1200 that I had experimented with many years ago. I did some research and it looked like it was going to be hard work to get it working with MBD images as the firmware doesn't natively support them. I did a bit of googling and found a custom firmware (FlashFloppy) for the Gotek USB Floppy Emulator which DID support the MBD image format. Amazing!

So I found that there are many clones of the Gotek floppy emulator available on Amazon. Even better. I ordered one that wasn't the cheapest, but also wasn't the most expensive - it came in at 24.99. One reason for ordering from Amazon is I know I can send it back with minimal fuss and get my money back if the device didn't work.

In true Amazon style, the floppy emulator arrived the next day with the standard Gotek firmware installed. OK, time to get this thing apart and see what's inside. To my surprise, the main chip used in the emulator I'd purchased was an Artery AT32F415 - supported by FlashFloppy! Amazing! I set about soldering a couple of headers to the board and making up the USB A -> USB A cable to flash it. The flashing worked first time! Wow! I hooked it up to my MB02+ and loaded some MBD images (bootdisks and demo disks) it all worked! Amazing! This felt like a much better way to experience those old floppies - in a RELIABLE way!

What was next? The FlashFloppy firmware has several hardware mods you can do, you can read more about them on the FlashFloppy wiki. So an hour or so later I had a Gotek USB Floppy Emulator with a 128x32 OLED display, rotary encoder (to select disk images) and a buzzer/speaker to make some floppy drive stepping sounds when the head is virtually being moved. It's REALLY nice.

So what does all of this mean? I've been using my old hardware again in a modern way! The MB02+ is an amazing piece of kit and really doesn't deserve to be locked away in a cupboard forever!

Next I plan to get another Gotek, flash it with FlashFloppy and attach it to my old +D interface as it supports MGT files too!

So get your hardware out and USE IT!

Links:

Dylan Smith's Spectranet Wiki:
 - https://spectrum.alioth.net/doc/index.php/Main_Page
Spectranet available from:
 - https://www.bytedelight.com/?product_cat=internetinterfaces
Ingo Truppel MB02+IDE interface photos (Omega's website):
 - https://omega.webnode.page/screen/photos/
Gotek USB Floppy Emulator:
 - https://www.gotekemulator.com/
Flash Floppy Firmware:
 - https://github.com/keirf/flashfloppy

LaesQ
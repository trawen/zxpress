```text
 ,_,                                                        __
 (o,o) -- -------------------------------------------- - -__/  \
{{ _ }} The ZX Sizif-512 - A Review by LaesQ             /  \__/
- "-" ------------------------------------------------ - \__/  \
The  ZX  Sizif-512 is a CPLD (Complex Programmable Logic    \__/
Device) based Speccy clone developed by Eugene Lozovoy (also known  as  UzixLS).  So  what's on offer here? It's a half-sized
(depth) 48K sized board that's designed to  fit  into  a  rubber keyed  48K case. You'll need to do some minor alterations to the case to accommodate the reset and NMI switches, the SD card slot and the video and joystick ports.
```

Hardware-wise, it contains a real Z80 CPU which can be clocked up to 14MHz. Normally a 20MHz CPU is used to allow these speeds without the need to overclock the CPU and introduce any instability to the system. RAM and ROM are both 512KB which allows for plenty of different ROMs along with the standard 128K RAM and 384K to be used by other systems which we'll get on to later.

Video-wise, it can output PAL RGB, Composite Video and also has an internal digital video header which can be used for EGA monitors, scandoublers, etc. It also contains ULAplus which is a standard that feels rather ignored by the demoscene, but it's nice to see it implemented here nonetheless. The video socket is a 9 pin mini-DIN connector using the same pinout as the Sega Mega Drive II. It carries stereo and mono audio signals along with RGB video signals. Pin 4 can be jumpered to carry Composite Sync or Composite Video signals.

For the audio buffs out there, it has a real AY-3-8910 which can be switched between ABC, ACB and mono output. It also contains a mono Covox and SounDrive (4 channel Covox). An additional internal audio addon board is also available that contains Turbo Sound FM, General Sound, SAA1099, MIDI sound all with real chips which is a nice feature. This slots horizontally into an internal connector and doubles the size of the board to be the same as a 48K board. This review does not cover this audio add-on board.

Inputs are a standard Speccy I/O port to connect all your existing hardware addons - the GitHub page has a list of tested hardware which is handy. It has the normal 5 x 8 keyboard headers so you can use a 48K keyboard. There's also a PS/2 keyboard header so you can use a PS/2 keyboard if you'd prefer. There's a 9 pin D connector for a joystick which can be configured as a Kempston or Sinclair joystick port. It also supports the Sega 6-button standard on the joystick port. You also have the option of using a standard 3.5mm tape input along with a Bluetooth interface header to allow a Bluetooth board with audio output to be connected. This allows audio to be played into the Speccy to load from via a Bluetooth device like a phone. There's also an optional WiFi interface that uses a custom board with an ESP8266 WiFi board on it. And of course, no system would be complete without a DivMMC running esxdos :)

The Sizif incorporates a clever "magic" menu which allows you to change the config of the machine on the fly. This menu can be invoked with a long press of the NMI button. Using this menu allows you to change the hardware model between 128K, 48K, +3e and Pentagon. These provide perfect timings for those hardware models, so watching demos timed to these machines is a pleasure. On top of this you can change the CPU clock speed from 3.5MHz up to 14MHz which is always fun to do with 3D demos and games. The menu also allows you to change the AY stereo mode (ABC/ACB/Mono), Joystick type (Kempston/Sinclair), 48K ROM used, DivMMC mode, ULA+ enabled, DAC mode, and some advanced options.

They're for extra settings like SD activity in the border, bright boost, and auto turbo mode (allows turbo mode when loading from an SD card). There are a couple of more obscure options, one to do with if you're using a ZXKit1 (a Pentagon based RGB > VGA scandoubler) and a "Map joy A as up" option.

Using a PS/2 keyboard with the Sizif-512 does open up a few extra possibilities. It allows you to press F-keys for reset, NMI and Magic (menu). But one interesting feature it allows is "fast forward" which cranks the CPU up to 14MHz to speed up what's going on. If your keyboard has a numpad you can use that as joystick input too.

The 512KB RAM is split up differently depending on your config. Of course, the 48K and 128K machine modes will use their respective 48K and 128K amounts of RAM. However, the DivMMC (if enabled) is assigned 128K of the RAM. Leaving an additional 128K to be used via port DFFDh. But if the DivMMC is disabled, 384KB of RAM is available to use via port DFFDh. The GitHub page does state the 128K RAM/128K DivMMC/128K DFFDh is the Profi standard and is most compatible with old 128K software.

So how does it compare to a genuine Sinclair Speccy? It's VERY good. For playing games and watching demos, it's spot on. Set which timings you want, load up the demo/game of your choosing and you won't know any different. All demos play as they should, all games play as they should. It really is a perfect system if you want a modern implementation of a 128K Speccy or Pentagon.

The only issue I've come across is the SounDrive mode appears to be quite quiet. This could be something I'm doing wrong or the implementation of it. I haven't had time to investigate this further.

What would I like to see improved? It would be nice to have a gigascreen detector that would merge the two switching screens when detected. This would give a nice flicker-free gigascreen output as some other interfaces have (like the MB03+ for example). It would also be good to have more RAM, mainly for the DivMMC. Something like 512KB would be nice, so it could cope with future enhancements that esxdos may provide. As it turns out, 128K for the DivMMC should be fine currently. Time will tell.

To sum up, there's a lot to like about the Sizif-512, it doesn't suffer from jail bars or need you to get out your soldering iron to get ACB stereo from your Speccy. Everything you probably would want from a 128K Speccy is there, it's all been very well thought out. I really like how you can change the stereo mode between ABC/ACB/Mono using a magic menu setting, so it's all managed by hardware. Add in the DivMMC paired with esxdos and you have a very nice Speccy system that can be used just like a real 128K Speccy and quite possibly more reliably. If you want to keep your genuine hardware in the cupboard but still use real hardware, you can't go far wrong with the Sizif-512. I'd be keen to hear from other readers who've tried the Sizif-512 and their experiences with it.

Where can you buy one? Well, they're a little trickier to find. In the UK there's an eBay user named jimmydeath (https://www.ebay.co.uk/usr/jimmydeath) who produces the boards in small batches and charges 125 GBP (+P&P) for the standard Sizif-512 board with the Bluetooth board, Wifi board and a PS/2 keyboard socket addon. He also includes an RGB to SCART lead to get you up and running quickly. For the amount of time this must take to source the parts and assemble them, I think it's rather good value for money. You'll need to get yourself a case and PSU, etc. but I'm sure most Speccy users will have something they can use lying about.

This review was based on the Sizif-512 Rev.E1 running CPLD and ROM firmware v20230820. My review is completely independent and I'm not affiliated with jimmydeath in any way.

Sizif-512 Github: https://github.com/UzixLS/zx-sizif-512
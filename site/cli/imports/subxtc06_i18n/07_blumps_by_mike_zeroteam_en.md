```text
----C__
 (o,o) -- -------------------------------------------- - -__/  \
{{ _ }} Blumps by mike/zeroteam                          /  \__/
- "-" ------------------------------------------------ - \__/  \
```

The history of Blumps begins a few years ago when TM wrote me an email. He was working on a puzzle game for Android and said he liked Pariboro and wondered if I'd be interested to remake his game for the Spectrum. We changed few emails and collaboration was established. Word got out and I started working on my next game - Blumps.

The initial engine was done relatively quickly. TM was working on the levels and drew the first graphics. Since he's not a ZX graphic artist, it had to be tweaked here and there. The bigger problem was with level editing. I thought editing the level as a set of DB values with human readable constants would be enough.

But the errors with typos, missing commas and so on were increasing, so I wrote a level editor in javascript and added an automatic build system from SVN. This way I could just work on the code and get error-free data from the level editor.

But as they say, 80% of the game takes 20% of the time and then development stops. The big hitch was the level flow (start game - level info - game - win/loose), which didn't look right for a long time. Then there was a problem with the graphics. TM didn't have time for it anymore and I was in a state where I couldn't even define what was wrong. Luckily Grongy helped with the graphics and the last touches were made by cvm.

Then I rushed z00m to make the opening beep music. And I shouldn't have done that. Don't get me wrong, the music is great. But it takes 17kB of memory, and thus I can't fit anything else in the game. So I'll have to use black magic to solve this problem.

So all that's left to do is to add the in-game music and sound effects, (re)define the levels and make a proper endgame. But you can already play 16 really hard levels in the demo-version.

The objective of the game is to get fields of the same color to touch each other with an edge. You are prevented from doing this by various other fields. You can read a detailed description by pressing the 2 (tutorial) key in the main menu. I'll be glad to receive any feedback.

mike/zeroteam
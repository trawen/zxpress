```text
____ __                                                      __
\  // / -- ------------------------------------------- - -__/  \
 \ \    Intervals with signed numbers by mike/zeroteam   /  \__/
--\ -------------------------------------------------- - \__/  \
                                                            \__/
```

While programming one effect, I ran into an interesting problem. I needed to find out if a signed number lies OUTSIDE a defined interval and if the value lies lower or higher of the interval. For demonstration, let's define it as <-6, 6).

The problem with signed numbers is, thanks to the two's complement, it works well for simple maths, but not for comparisons.

When we compare unsigned numbers, after CP 6 is clear if the number is smaller when CARRY flag is set and greater or equal to if it's not set. However, with sign comparison arises a problem that all numbers with a sign are evaluated as larger aswell.

Let's try to make a table of comparison results for each part of the interval (0 means NC, 1 means C):

```text
            -6     0     6
        -----<-----|-----)-----
CP  6     0     0     1     0
CP -6     1     0     1     1
```

```text
 ________,-----,
 \__          /----,____,----,__  _____,----,____,----,___,----,
   /    /____/    /    /        \/    /              /         /
  /____     /    /    /    '   _/    '____/   _,    /    /__ _/
 /    /    /    /    /    /   <   /   \ /    /    /    /
/____     /________./____     /\___/    /    /    /\___     /
    /____/              `----'    /____/K-0s/____/    /____/
```

Neither comparison fits. We could already be throwing in the towel, but as experienced demomakers, we can see that we get the desired result by function:

(CP 6) XOR (CP -6)

Thus the CARRY flag tells us if the value is out of range and the sign value indicates whether the result is below or above the interval. But the problem with XOR is we need to save the results of both comparisons in registers, perform bitwise operation and process the result. But if we replace the XOR function with a simple addition, the result would be the same, and this is what the instruction ADC is offering us. So what does my implementation attempt look like?

```assembly
   ld   b,a   ;4T
   cp   6     ;7T
   rl   c     ;8T
   cp   -6    ;7T
   ld   a,c   ;4T
   adc  a,0   ;7T   this is the XOR of 2 comparations
   rra        ;4T
   sbc  a,a   ;4T
   ret  c     ;11/5T
   and  1     ;7T
   rl   b     ;8T
   adc  a,0   ;7T
   ret        ;10T
              ;=56/82T
```

The input is expected in register A and the result is again in register A, bit 1 set means a value is lower than the interval and bit 0 set means value greater than the interval (remember I wrote that my main concern is to find out if the value lies OUTSIDE the interval?). In addition, the routine is beautifully cryptographic and hard to understand. Nice work mikezt!

So let's try it again and less complicated this time. Let's move the interval up a whole negative fraction:

Original values:     -6     0     6
                 -----<-----|-----)----
Shifted values:       0     6    12

Here it is already clear it is enough to compare the shifted value with the length interval and again the sign of the original value will tell us whether the the number is below or above the interval. The implementation?

```assembly
   ld   c,0   ;7T
   add  a,6   ;7T
   cp   12    ;7T
   ret  c     ;11/5T
   inc  c     ;4T
   sub  6     ;7T
   ret  m     ;11/5T
   inc  c     ;4T
   ret        ;10T
              ;=32/47/54T
```

The input is again in register A, but the output is this time in register C. And as you can see the routine is shorter, faster and more readable.

Can it be improved? It certainly can. Using a table the result looks like this:

```assembly
   ld   h,CP_TAB/256   ;7T
   ld   l,a            ;4T
   ld   a,(hl)         ;7T
                       ;=18T
```

I hope you have enjoyed discovering the peripeteia of signed numbers with me and I'll see you next time.

mike/zeroteam
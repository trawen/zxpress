```text
____ __                                                      __
\  // / -- ------------------------------------------- - -__/  \
 \ \    Did you know...? by mike/zeroteam                /  \__/
--\ -------------------------------------------------- - \__/  \
                                                            \__/
```

Did you know that there are differences between instructions `RLA` (opcode `#17`) and `RL A` (opcode `#CB #17`)? While `RLA` is only one byte long and lasts 4 clock cycles, `RL A` is two bytes long and lasts 8 clock cycles. Additionally, `RLA` only sets the Carry flag according to the high bit 7 and sets the H and N flags to zero.

`RL A` additionally sets the S flag if the result was negative, the Z flag if the result was zero, and the P/V flag according to the parity of the result.

mikezt
Flashing Light Prize 2017
@RobSmithDev

Task is to create "something" that flashes an incandecent bulb between 0.5hz and 2hz

I decided to go for a really crazy long-winded stupid solution to the problem

The following sequence creates the flashing light:


The circuits involved in the flashing light prize 2017
This was the most elaborate and badly made circuits I could make with the bits lying around.

Entire setup is:

1. Music is played into a microphone
2. Microphone connected to 741 Preamp circuit with simple low pass filter
3. 741 op-amp acts as a peak detector (using pot to trim to desired level)
4. Fed into a 555 one-shot monostable to produce pulses 
5. Pulses turn on another 555 which generates an audible tone
6. Tone is fed into a 2.5Ghz audio/video sender
7. Tone is received by a 2.5Ghz audio/video receiver and fed into the Line In on the computer
8. An HTML5 page receives the audio, detects the pulses and calculates the BPM
9. The BPM is then predicted when the audio contains no beat 
10. The HTML5 page connects to a web server via Web Sockets
11. Each time a beat is detected or simulated the phrase "BEAT" is sent
12. The server monitors connections and when it receives the phrase "BEAT" it transmits the phrase "Beat" (note different capitalization) to all other connected sockets.
13. A second web page fed from the web server is loaded on my mobile phone
14. When it receives the word "Beat" from the server it toggles the screen white or black
15. A solar cell sits on the phone screen, and when white triggers an op amp to swing full on or off
16. The op-amp output is fed into a transistor
17. The transistor switches on and off a relay
18. The relay when switched in one direction charges a capacitor from a 9V battery
19. The relay when switched in the other direction discharges it into the lamp!

Full circuit diagrams available at https://easyeda.com/RobSmithDev/Flashing_Light_Challenge_Preamp-f1647f226bef49c69359fe5471c99b18
Video of it working at https://youtu.be/a4y7WOAJmn4
Source code should have been found at https://github.com/RobSmithDev/FlashingLightPrize2017

Simple eh

RobSmithDev
http://www.robsmithdev.co.uk
 
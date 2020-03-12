![Unit Testing](https://github.com/JoshPiper/PHP-InterCord/workflows/Unit%20Testing/badge.svg)
[![codecov](https://codecov.io/gh/JoshPiper/PHP-InterCord/branch/master/graph/badge.svg)](https://codecov.io/gh/JoshPiper/PHP-InterCord)

## InterCord
InterCord is a simple PHP Discord Webhooks & Rich Embed library.

Webhooks are created from either their URL or ID/Token pair.
They are executed with embeds, content or strings.

### Vendor Prefix
All class names are in the \Internet\InterCord\ namespace.

### Classes
Classes are split into two main types, internal and external.
Internal classes are generally used for hiding backend processes, such as converting colours to decimal or providing json encodings.
External classes are generally used for developer facing processes, such as executing webhooks or providing rich embeds.

### External
`Webhook` is a class which represents a single webhook endpoint.  
`RichEmbed` is a class which repersents rich embeds, unsuprisingly, and is an easy to use, chainable class to make embeds easier.  

### Internal
`Embed*` are a series of classes used to recieve and store data about parts of an embed.  
`Payload` is a class which stores payloads before they are executed. These can be used for sending data to multiple webhooks, or with minor changes.  
`Color` is a class representing a colour structure. It can be created from R/G/B values, hex codes or decimal numbers.  

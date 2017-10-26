# PHPBrowser and BrowserShell

A scriptable browser in PHP and his CLI !

You can browse to the web, and fill and submit forms.

You can call the browser's methods from your PHP scripts,
or you can execute them from STDIN ou from a batch file.

Browser.php is the browser, to make web requests.
BrowserShell.php is a shell to control Browser.php



## Usage

Browser on your PHP script:

    require_once 'lib/Browser.php'

Browser from STDIN

    php BrowserShell.php --stdin

Browser with batch file

    php BrowserShell.php --file=<batch file> [--config=<config file>] [--pause=<delay beetween network requests>] [--debug=1|0]

### Methods of BrowserShell

    browse   : Browse to an URL
    submit   : Submit a form. Can be followed by DOM-Element's name or #ID (start with #)
    write    : Write the response's body to file
    print    : Show  the response's body
    history  : Browse in browser's history
    use-form : Select a form to use (by name or ID. If empty, use the first form)
    reset    : Reset the form
    set      : Set a field value by name = value or #ID = value
    unset    : Remove a field element of the sent data
    check    : Check the checkbox (by name or #ID).   Equivalent of set NameOr#ID on
    uncheck  : Uncheck the checkbox (by name or #ID). Equivalent of unset NameOr#ID
    set-var  : Set a reusable script variable will be call by {%var:var-name%}
    include  : Include and execute an another batch file
    message  : Print a message
    debug    : Print a message if debug=1

Examples

    browse http://www.google.fr  Load webpage http://www.google.fr
    
    include commandes.browser    Run commands wich are defined in commandes.browser file 
    
    use-form             Select the first form (optional. It is automatic)
    use-form formName    Select the form with name "formName"
    use-form #formID     Select the form with ID "formID"
    
    submit               Submit form
    submit submitButton  Submit form with button with name "submitButton"
    submit #submitBtnId  Submit form with button with ID "submitBtnId"
    
    write  filePath      Write the responses' body to "filePath"
    
    history goto:[label]   Go to label "label" (like a click in your browser's history)
    history -1             Go back to the previous page - Resend the form
    history back           Go back to the previous page - Resend the form
    history +1             Go to the next page - Resend the form
    history forward        Go to the next page - Resend the form
    history 0              Reload current page - Resend the form
    history reload         Reload current page - Resend the form
    history -5             Go to 5 previous pages - Resend the form
    history +2             Go to 2 following pages - Resend the form
    history [param] browse Like "history ... " but with a GET request (no re-sending the form)
    
    reset                  Reset current form
    
    set name = value        Set "value" to the field named "name"
    set #id  = value        Set "value" to the field with ID "id"
    set name = ["a", "b"]   Set an array of values to field named "name"
    set name = "["a", "b"]" Set a string value '["a", "b"]' to the field named "name"
    
    check mycheckbox       Check the checkbox named "mycheckbox"
    check #checkboxUD      Check the checkbox with id "checkboxID"
    uncheck mycheckbox     Uncheck the checkbox named "mycheckbox"
    uncheck #checkboxUD    Uncheck the checkbox with id "checkboxID"
    
    unset name             The field named "name" will not sent on submit
    unset #id              The field with ID "id" will not sent on submit
    
    set-var variable = val Set the value "val" to variable named "variable". {%var:variable%} will return "val"
    
    print my message       Show "my message" with line feed
    print                  Show only an empty line
    
    debug debug message    Show "debug message" if debug = 1
    debug                  Show an empty line if debug = 1

### Labels :

You can use Label / Commands' group with :

    [label]

You can use go to label (browser's history) with :

    history goto:[label]

You can replay the commands' group with :

    play label

Example :

    [label1]
    
    browse http://www.google.fr
    write my-file-1.htm
    
    [label2]
    
    browse http://www.php.net
    write my-file-2.htm
    play label1
    
    [end-label-2]
    
    play label2

Is equivalent to :

    [label1]
    
    browse http://www.google.fr
    write my-file-1.htm
    
    [label2]
    
    browse http://www.php.net
    write my-file-2.htm
    
    browse http://www.google.fr
    write my-file-1.htm
    
    [end-label-2]
    
    browse http://www.php.net
    write my-file-2.htm
    
    browse http://www.google.fr
    write my-file-1.htm


### Usable variables :

    {%system:login%}
    {%system:timestamp%}
    {%system:os%}
    {%system:rand%}
    {%system:rand:min:max%}

    {%date:format%}         Warning: format use french character ! AAAA = Year on 4 char, JJ = day on 2 char, etc.
    {%date:php:format%}     To use directly the PHP format like date() function
    {%date_interval:interval:format%}

    {%cfg:config-field-name%} Return the value of the field "config-field-name" from the section [data] of the config file

    {%var:var-name%}          Return the value of the variable "var-name" defined by the command "set-var var-name = value"

Note :
Variables can be nested :

    {%cfg:constant-string{%var:var-name%}%}


## Example
    
    
### Configuration file
 
    ; --------------------------------------------------------
    ; Example of INI configuration file
    ;
    ; --------------------------------------------------------
	
	[browser]
	user-agent="Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36"
	cookie-file=librx.cookie
	exit-if-error=1
	
	
	[shell]
	
	pause-duration=1
	check-formelement-exists=1
	check-formelement-type=1
	debug=0
	
	[data]
	
	my-search-1 = scriptable browser
	my-search-2 = download php for windows
    
### Commands file

	; --------------------------------------------------------
	; Example of batch file
	;
	; --------------------------------------------------------
	
	; Set custom variables
	
	set-var search-num  = 1
	
	[init]
	
	browse http://www.google.fr
	
	[search1]
	
	; search text on Google and submit with "I'm feeling lucky" button
	
	set-var search-text = {%cfg:my-search-{%var:search-num%}%}
	
	set q = {%var:search-text%}
	submit btnI
	
	[download]
	
	; write website data to file
	
	write ./google_search{%var:search-num%}.html
	
	[new-search]
	
	set-var search-num  = 2
	    
	history goto:[search1]
	
	play search1
	play download


WARNING !
If "play" command is in his own command groups, there is an infinite loop !

    [myloop]

    ...	
    
    play myloop
    ...
    
Is an infinite loop !

You must use :

    [myloop]

    ...	

    [another-label]
       	
    play myloop
    ...



## Configuration file

There are 3 sections :

[browser] for the browser configuration :
user-agent = User-Agent
cookie-file = Cookie file to user 
exit-if-error = 1 to exit in case of server error (not 2xx or 1xx or 3xx code)

[shell] for the shell configuration
pause-duration = (duration in seconds after each network call)
check-formelement-exists = 1 to set field value only if element exists in DOM
check-formelement-type = 1 to check/uncheck checkbox only if the element is a checkbox
debug = 1 to show execution messages

[data] for your custom data

custom-field = custom-value

theses fields will be use in batch file and config file with {%cfg:field-name%}
You can use variables, functions and config fields in the [data] section

For example :

    [data]
    
    file-name = /home/path/PREFIX_MYFILE_DATA-{%date:php:Y-m-d_His%}-{%system:os%}.EXT

If the date is 2017-11-01 15:26, and the OS is linux,
{%cfg:file-name%} returns "/home/path/PREFIX_MYFILE_DATA-2017-11-01 15:26-linux.EXT"

The variables can be nested :

For example :

    [data]
 
    date-format = php:Y-m-d_His
    file-name = /home/path/PREFIX_MYFILE_DATA-{%date:{%cfg:date-format%}%}-{%system:os%}.EXT


## License

[GNU / LGPL v.3](https://www.gnu.org/licenses/gpl.html)

    Copyright (C) 2017 Francois RAOULT

    Licensed under the LGPL, Version 3.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at

       https://www.gnu.org/licenses/gpl.html

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.

## Contributing

Please fork this repository and contribute back using [pull requests].

Any contributions, large or small, major features, bug fixes, unit tests are welcomed and appreciated.



## Author

Francois Raoult | http://francois.raoult.name

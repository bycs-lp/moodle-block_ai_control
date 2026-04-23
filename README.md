# block_ai_control - Control center for managing access to AI tools from inside a course

This plugin provides teachers the possiblity to configure the access to AI tools from inside a course.

Please be aware that this is only compatible with the AI tools suite around the local_ai_manager backend,
not the moodle core AI subsystem.

Plugins this is compatible with are for example:
- block_ai_chat
- tiny_ai
- qtype_aitext

## Screenshot ##

<div align="center">

![block_ai_control](docs/block_ai_control.png)

</div>

## Requirements

https://github.com/mebis-lp/moodle-local_ai_manager needs to be installed.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/blocks/ai_control

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.


## Using it in a course ##

By default the installation of this plugin locks access to AI tools inside a course
for student users (users without the capability "block/ai_control:control").

As an instructor you can add an instance of the AI control center block to your course
by ticking the checkbox in the course settings (section "AI functionalities"). Now, you
can enable your students to use AI tools inside the course.

## License ##

2024 ISB Bayern

Lead developer: Philipp Memmel <philipp.memmel@isb.bayern.de>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.

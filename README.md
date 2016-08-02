# ADP eTime API

A small API for accessing certain function of ADP eTime.  
[![license](https://img.shields.io/github/license/therealgitcub/adp-etime-api.svg?maxAge=60)](https://github.com/TheRealGitCub/adp-etime-api/blob/master/LICENSE)
[![GitHub release](https://img.shields.io/github/release/therealgitcub/adp-etime-api.svg?maxAge=2592000)](https://github.com/therealgitcub/adp-etime-api/releases)
[![GitHub tag](https://img.shields.io/github/tag/therealgitcub/adp-etime-api.svg?maxAge=2592000)](https://github.com/therealgitcub/adp-etime-api/releases)

## To-do List

- [x] ~~Clock In/Out~~
	- [x] ~~Implement return message~~
- [x] ~~View Timecard~~
	- [x] Approve Timecard (untested)
		- [ ] Improve error checking
	- [x] ~~Check if clocked in and when~~
	- [x] Check for missed punches (untested)
- [ ] Bring up to JSON API standards

## Requirements

Requires [Simple HTML DOM](http://simplehtmldom.sourceforge.net/) to function.
Path may need to be changed based on your setup. See `adp.php`

## Methods

| Method              | Description    | Data Example   |
| :------------------ | :------------- | :---------      |
| `record-stamp`      | Adds a new timestamp to the user's timecard  | `{status: "[OK/FAILED]" [, message: ""]}` |
| `view-timecard`	  | Shows the user's timecard in JSON format | `{total: "5:00", period: ""7/30/2016 - 8/12/2016", shifts: [...]}` |
| `clocked-in` 		  | Check if the user is clocked into eTime | `{"clockedIn":true,"at":"9:30AM"}` |
| `missed-punch`	  | Check if the user has missed a punch in the current pay period |  `{"missedPunch":false}` |
| `approve-timecard`  | **Untested Method**<br /> Submit the user's timecard for manager approval | `{status: "[OK/FAILED]" [, message: ""]}` |


```
Software licensed under GNU GPL v3. ADP and Enterprise eTime are registered
trademarks of ADP, LLC. ADP eTime API and Kobi Tate (TheRealGitCub) are NOT
affiliated with ADP, LLC. This software is NOT associated with nor endorsed
by ADP, LLC. Use of this API may violate ADP eTime terms and conditions.
Developer assumes no responsibility for any repercussions from the use of
the software. Use at your own risk.
```

vex is a small PHP app that sends some load to a web application

## Installation

Download the latest release from GitHub ['Releases']('/releases').

## Usage

Usage:
``  ./vex.phar vex <url> [<n>] [<c>] [<m>] ``

Arguments:
```
 url                   The URL to which the requests should be sent
  n                     Number of requests to be made [default: 1]
  c                     Concurrency [default: 1]
  m                     HTTP Method [default: "GET"]
```
Example :

./vex.phar vex http://127.0.0.1:8000 1000 10

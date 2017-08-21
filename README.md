vex is a small PHP app that sends some load to a web application

![vex - PHP based HTTP load generator](https://i.imgur.com/Pat80U1.gif "vex - PHP HTTP Load Generator")

## Installation

Download the latest release from GitHub [Releases](https://github.com/vamsiikrishna/vex/releases).





## Usage

Usage:
``  vex [options] [--] <url> [<n>] [<c>]
 ``

Arguments:
```
  url                      The URL to which the requests should be sent
  n                        Number of requests to be made [default: 1]
  c                        Concurrency [default: 1]
```
Options:
```
  -m, --method[=METHOD]    HTTP Method [default: "GET"]
  -H, --headers[=HEADERS]  Headers (multiple values allowed)
  -d, --body[=BODY]        Request body
```
Example :

- 1000 Get requests with 10 concurrency to `http://127.0.0.1:8000`

`./vex.phar vex http://127.0.0.1:8000 1000 10`

- 1000 POST requests with 10 concurrency to `http://127.0.0.1:8000` with custom headers and body

```
./vex.phar vex http://127.0.0.1:8000 1000 10 \
-m "POST" \
-H "accept:application/json, text/plain, */*" \
-H "accept-language:en-IN,en-GB;q=0.8,en-US;q=0.6,en;q=0.4" \
-d "{\"message\": \"Hello world! Your JustAPIs instance is running correctly.\"}"
```
vex is a small PHP app that sends some load to a web application

![vex - PHP based HTTP load generator](https://i.imgur.com/Pat80U1.gif "vex - PHP HTTP Load Generator")

## Installation

Download the latest release from GitHub [Releases](https://github.com/vamsiikrishna/vex/releases).

Or require globally using Composer with `composer global require vamsiikrishna/vex`. This will automatically add the `vex` binary to your path.

## Usage

Usage:  
`vex [options] [--] <url> [<n>] [<c>]`

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
  -f, --format[=FORMAT]    Output format (text, json) [default: "text"]
      --no-report          Disable the detailed report output
```

## Results and Statistics

vex provides detailed performance statistics after completing the requests:

- **Response Time Statistics**: Minimum, maximum, average, and total response times
- **Request Rate**: Number of successful requests per second
- **Status Code Distribution**: Breakdown of responses by HTTP status code
- **Visual Charts**: Bar chart showing status code distribution by group (2xx, 3xx, 4xx, 5xx)
- **Error Summary**: Details about any failed requests

### Output Formats

- **Text** (default): Human-readable formatted output with colors and charts
- **JSON**: Machine-readable format ideal for further processing or logging

Example with JSON output:
```
./vex.phar vex http://127.0.0.1:8000 100 10 --format json
```

## Examples

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

- Run benchmark with JSON output for integration with other tools

```
./vex.phar vex http://127.0.0.1:8000 1000 10 --format json > results.json
```
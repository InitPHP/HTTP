# HTTP Status Codes

The `Response::PHRASES` table maps status codes to their IANA-registered reason phrases. When you construct a `Response` without an explicit reason, the corresponding phrase from this table is used.

| Code | Reason Phrase                       |
|------|-------------------------------------|
| 100  | Continue                            |
| 101  | Switching Protocols                 |
| 102  | Processing                          |
| 103  | Early Hints                         |
| 200  | OK                                  |
| 201  | Created                             |
| 202  | Accepted                            |
| 203  | Non-Authoritative Information       |
| 204  | No Content                          |
| 205  | Reset Content                       |
| 206  | Partial Content                     |
| 207  | Multi-status                        |
| 208  | Already Reported                    |
| 210  | Content Different                   |
| 226  | IM Used                             |
| 300  | Multiple Choices                    |
| 301  | Moved Permanently                   |
| 302  | Found                               |
| 303  | See Other                           |
| 304  | Not Modified                        |
| 305  | Use Proxy                           |
| 306  | Switch Proxy                        |
| 307  | Temporary Redirect                  |
| 308  | Permanent Redirect                  |
| 400  | Bad Request                         |
| 401  | Unauthorized                        |
| 402  | Payment Required                    |
| 403  | Forbidden                           |
| 404  | Not Found                           |
| 405  | Method Not Allowed                  |
| 406  | Not Acceptable                      |
| 407  | Proxy Authentication Required       |
| 408  | Request Time-out                    |
| 409  | Conflict                            |
| 410  | Gone                                |
| 411  | Length Required                     |
| 412  | Precondition Failed                 |
| 413  | Request Entity Too Large            |
| 414  | Request-URI Too Large               |
| 415  | Unsupported Media Type              |
| 416  | Requested range not satisfiable     |
| 417  | Expectation Failed                  |
| 418  | I'm a teapot                        |
| 421  | Misdirected Request                 |
| 422  | Unprocessable Entity                |
| 423  | Locked                              |
| 424  | Failed Dependency                   |
| 425  | Unordered Collection                |
| 426  | Upgrade Required                    |
| 428  | Precondition Required               |
| 429  | Too Many Requests                   |
| 431  | Request Header Fields Too Large     |
| 451  | Unavailable For Legal Reasons       |
| 500  | Internal Server Error               |
| 501  | Not Implemented                     |
| 502  | Bad Gateway                         |
| 503  | Service Unavailable                 |
| 504  | Gateway Time-out                    |
| 505  | HTTP Version not supported          |
| 506  | Variant Also Negotiates             |
| 507  | Insufficient Storage                |
| 508  | Loop Detected                       |
| 510  | Not Extended                        |
| 511  | Network Authentication Required     |

## Custom phrases

Pass a reason explicitly when you need something off-canon:

```php
$response = new Response(418, [], null, '1.1', "I'm a coffee maker, actually");
$response = $response->withStatus(599, 'Network connect timeout error');
```

Status codes are validated against the inclusive range 100..599. Anything else raises `InvalidArgumentException`.

## Codes outside the table

Codes that aren't in the table get an empty reason phrase. Many proxies and clients are fine with that; some refuse to parse a status line without a phrase. Pick a phrase yourself if you're emitting a non-standard code:

```php
$response = (new Response())->withStatus(599, 'Network connect timeout error');
```

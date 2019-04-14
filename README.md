# Commerce Cart API

Sandbox to provide a RESTful interface to interact with carts in Drupal Commerce via a lightweight public API.

## How to test it out

* Clone down this repository into your Drupal Commerce TESTING PROJECT
* Add `$settings['extension_discovery_scan_tests'] = TRUE;` to your settings.php
* Enable `commerce_cart_api`
* Enabled `commerce_cart_reactjs`
* Remove default cart block with `commerce_cart_reactjs`
* Cart form automatically swapped.

## GET (carts)

```bash
curl 'http://localhost:32775/jsonapi/cart'
```

Example JSON response for above.

```json
{
    "jsonapi": {
        "version": "1.0",
        "meta": {
            "links": {
                "self": {
                    "href": "http://jsonapi.org/format/1.0/"
                }
            }
        }
    },
    "data": [
        {
            "type": "commerce_order--physical",
            "id": "8372df65-f362-4c78-8bcd-76e01cf55912",
            "attributes": {
                "drupal_internal__order_id": 6,
                "order_number": null,
                "total_price": {
                    "number": "156.000000",
                    "currency_code": "USD",
                    "formatted": "$156.00"
                }
            },
            "relationships": {
                "store_id": {
                    "data": {
                        "type": "commerce_store--online",
                        "id": "60ca3e21-d64c-451e-93e3-1fda1ff8cb93"
                    },
                    "links": {
                        "self": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order/physical/8372df65-f362-4c78-8bcd-76e01cf55912/relationships/store_id"
                        },
                        "related": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order/physical/8372df65-f362-4c78-8bcd-76e01cf55912/store_id"
                        }
                    }
                },
                "coupons": {
                    "data": [],
                    "links": {
                        "self": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order/physical/8372df65-f362-4c78-8bcd-76e01cf55912/relationships/coupons"
                        },
                        "related": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order/physical/8372df65-f362-4c78-8bcd-76e01cf55912/coupons"
                        }
                    }
                },
                "order_items": {
                    "data": [
                        {
                            "type": "commerce_order_item--physical_product_variation",
                            "id": "55722e66-0e66-48f5-98a6-5fd61facdaa8"
                        }
                    ],
                    "links": {
                        "self": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order/physical/8372df65-f362-4c78-8bcd-76e01cf55912/relationships/order_items"
                        },
                        "related": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order/physical/8372df65-f362-4c78-8bcd-76e01cf55912/order_items"
                        }
                    }
                }
            },
            "links": {
                "self": {
                    "href": "http://commerce2x.ddev.local/jsonapi/commerce_order/physical/8372df65-f362-4c78-8bcd-76e01cf55912"
                }
            }
        }
    ],
    "links": {
        "self": {
            "href": "http://commerce2x.ddev.local/jsonapi/cart"
        }
    }
}
```

## POST

Example to add items to cart. Creates or carts or adds to existing. Returns the affected order item or items.

```bash
curl -X POST \
  'http://localhost:32775/jsonapi/cart/add' \
  -H 'Content-Type: application/vnd.api+json' \
  -d '{
    "data": [
        {
            "type": "commerce_product_variation--clothing",
            "id": "2a242d40-c483-4deb-bab5-1eab4e715a28"
        }
    ]
}'
```

Response

```json
{
    "jsonapi": {
        "version": "1.0",
        "meta": {
            "links": {
                "self": {
                    "href": "http://jsonapi.org/format/1.0/"
                }
            }
        }
    },
    "data": [
        {
            "type": "commerce_order_item--physical_product_variation",
            "id": "55722e66-0e66-48f5-98a6-5fd61facdaa8",
            "attributes": {
                "drupal_internal__order_item_id": 7,
                "title": "Drupal Commerce Hoodie - Blue, Small",
                "quantity": "4",
                "unit_price": {
                    "number": "52.000000",
                    "currency_code": "USD",
                    "formatted": "$52.00"
                },
                "total_price": {
                    "number": "208.00",
                    "currency_code": "USD",
                    "formatted": "$208.00"
                }
            },
            "relationships": {
                "order_id": {
                    "data": {
                        "type": "commerce_order--physical",
                        "id": "8372df65-f362-4c78-8bcd-76e01cf55912"
                    },
                    "links": {
                        "self": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order_item/physical_product_variation/55722e66-0e66-48f5-98a6-5fd61facdaa8/relationships/order_id"
                        },
                        "related": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order_item/physical_product_variation/55722e66-0e66-48f5-98a6-5fd61facdaa8/order_id"
                        }
                    }
                },
                "purchased_entity": {
                    "data": {
                        "type": "commerce_product_variation--clothing",
                        "id": "2a242d40-c483-4deb-bab5-1eab4e715a28"
                    },
                    "links": {
                        "self": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order_item/physical_product_variation/55722e66-0e66-48f5-98a6-5fd61facdaa8/relationships/purchased_entity"
                        },
                        "related": {
                            "href": "http://commerce2x.ddev.local/jsonapi/commerce_order_item/physical_product_variation/55722e66-0e66-48f5-98a6-5fd61facdaa8/purchased_entity"
                        }
                    }
                }
            },
            "links": {
                "self": {
                    "href": "http://commerce2x.ddev.local/jsonapi/commerce_order_item/physical_product_variation/55722e66-0e66-48f5-98a6-5fd61facdaa8"
                }
            }
        }
    ],
    "links": {
        "self": {
            "href": "http://commerce2x.ddev.local/jsonapi/cart/add"
        }
    }
}
```


## PATCH

@todo update for JSON API

A JSON object of order item IDs whose values are objects that define the item's new quantity.

```
{
  3: {"quantity": 1},
  9: {"quantity": 2},
}
```


```bash
curl 'http://localhost:32775/cart/1/items?_format=json' -X PATCH \
    -H 'Content-Type: application/json' \
    --data-binary '{"2":{"quantity":"1"},"3":{"quantity":"1.00"}}'
```

Response

```json
{
  "order_id": 2,
  "uuid": "be9cb309-2c77-4f85-9444-013c91ab9318",
  "order_number": null,
  "store_id": 1,
  "total_price": {
    "number": "303.98",
    "currency_code": "USD"
  },
  "order_items": [
    {
      "order_item_id": 9,
      "uuid": "dcaa6901-f799-4505-af4c-9d1db9a85bbb",
      "type": "physical_product_variation",
      "purchased_entity": 4,
      "title": "Drupal Commerce Hoodie - Green, Small",
      "quantity": "2.00",
      "unit_price": {
        "number": "52.00",
        "currency_code": "USD"
      },
      "total_price": {
        "number": "104.00",
        "currency_code": "USD"
      }
    },
    {
      "order_item_id": 21,
      "uuid": "3bb80d13-ec7d-4e69-af33-a2f014c5e284",
      "type": "physical_product_variation",
      "purchased_entity": 15,
      "title": "Pronto600 Instant Camera",
      "quantity": "2.00",
      "unit_price": {
        "number": "99.99",
        "currency_code": "USD"
      },
      "total_price": {
        "number": "199.98",
        "currency_code": "USD"
      }
    }
  ]
}
```

## DELETE

@todo update for JSON API

Removes an order item from the cart.

```bash
curl 'http://localhost:32775/cart/2/items/9?_format=json' -X DELETE
```

Response

No body, just HTTP code.

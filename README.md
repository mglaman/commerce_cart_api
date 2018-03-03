# Commerce Cart API

Sandbox to provide a RESTful interface to interact with carts in Drupal Commerce via a lightweight public API.

## GET (carts)

```bash
curl 'http://localhost:32775/cart?_format=json'
```

Example JSON response for above.

```json
[
  {
    "order_id": 2,
    "uuid": "be9cb309-2c77-4f85-9444-013c91ab9318",
    "order_number": null,
    "store_id": 1,
    "total_price": {
      "number": "403.97",
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
        "quantity": "3.00",
        "unit_price": {
          "number": "99.99",
          "currency_code": "USD"
        },
        "total_price": {
          "number": "299.97",
          "currency_code": "USD"
        }
      }
    ]
  }
]

```

## POST

Example to add items to cart. Creates or carts or adds to existing.

```bash
curl -X POST \
  'http://localhost:32775/cart/items?_format=json' \
  -H 'Content-Type: application/json' \
  -d '[{    "purchased_entity": "6",    "quantity": "1"}]'
```

Response

```json
[
    {
        "order_id": 7,
        "uuid": "d0e68a71-780d-422e-b951-5ffc980bd296",
        "order_number": null,
        "store_id": 1,
        "total_price": {
            "number": "174.00",
            "currency_code": "USD"
        },
        "order_items": [
            {
                "order_item_id": 26,
                "uuid": "ec920271-51f7-4869-8e8f-46bdd733ba58",
                "type": "physical_product_variation",
                "purchased_entity": 21,
                "title": "24\" x 36\" Hot Air Balloons",
                "quantity": "2.00",
                "unit_price": {
                    "number": "35.00",
                    "currency_code": "USD"
                },
                "total_price": {
                    "number": "70.00",
                    "currency_code": "USD"
                }
            },
            {
                "order_item_id": 27,
                "uuid": "ac0a2890-38ab-4332-8425-8df7f1458552",
                "type": "physical_product_variation",
                "purchased_entity": 6,
                "title": "Drupal Commerce Hoodie - Green, Large",
                "quantity": "2.00",
                "unit_price": {
                    "number": "52.00",
                    "currency_code": "USD"
                },
                "total_price": {
                    "number": "104.00",
                    "currency_code": "USD"
                }
            }
        ]
    }
]
```


## PATCH

An array of order item data. The minimum data in order item for update

* order_item_id
* type

Currently only `quantity` is respected.

```bash
curl 'http://localhost:32775/cart/2/items?_format=json' -X PATCH \
    -H 'Content-Type: application/json' 
    --data-binary '[{"order_item_id":9,"uuid":"dcaa6901-f799-4505-af4c-9d1db9a85bbb","type":"physical_product_variation","purchased_entity":4,"title":"Drupal Commerce Hoodie - Green, Small","quantity":"2.00","unit_price":{"number":"52.00","currency_code":"USD"},"total_price":{"number":"104.00","currency_code":"USD"}},{"order_item_id":21,"uuid":"3bb80d13-ec7d-4e69-af33-a2f014c5e284","type":"physical_product_variation","purchased_entity":15,"title":"Pronto600 Instant Camera","quantity":"2","unit_price":{"number":"99.99","currency_code":"USD"},"total_price":{"number":"299.97","currency_code":"USD"}}]'
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

Removes an order item from the cart.

```bash
curl 'http://localhost:32775/cart/2/items/9?_format=json' -X DELETE
```

Response

No body, just HTTP code.

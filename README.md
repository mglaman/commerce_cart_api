# Commerce Cart API

Sandbox to provide a RESTful interface to interact with carts in Drupal Commerce via a lightweight public API.

## GET

* GET `/cart?_format=json`
* GET `/carts/{commerce_order}/items?_format=json`

Example JSON response for above.

```json
{
  "order_id": [
    {
      "value": 1
    }
  ],
  "uuid": [
    {
      "value": "f5e1c839-df12-45c3-9491-5f0aaf4ba8c6"
    }
  ],
  "type": [
    {
      "target_id": "physical",
      "target_type": "commerce_order_type",
      "target_uuid": "a34d462f-61f9-4b6c-b422-39ea178b18e0"
    }
  ],
  "order_number": [],
  "store_id": [
    {
      "target_id": 1,
      "target_type": "commerce_store",
      "target_uuid": "9f91fc5b-e26c-455d-b041-9cf9e57586fb",
      "url": "\/store\/1"
    }
  ],
  "total_price": [
    {
      "number": "52.00",
      "currency_code": "USD"
    }
  ],
  "order_items": [
    {
      "order_item_id": [
        {
          "value": 1
        }
      ],
      "uuid": [
        {
          "value": "7cf02b27-5865-4bfe-916b-48f2124a28f4"
        }
      ],
      "type": [
        {
          "target_id": "physical_product_variation",
          "target_type": "commerce_order_item_type",
          "target_uuid": "7a6b2d03-e581-4f2b-9c4e-81f870b0952d"
        }
      ],
      "purchased_entity": [
        {
          "target_id": 1,
          "target_type": "commerce_product_variation",
          "target_uuid": "2a242d40-c483-4deb-bab5-1eab4e715a28"
        }
      ],
      "title": [
        {
          "value": "Drupal Commerce Hoodie - Blue, Small"
        }
      ],
      "quantity": [
        {
          "value": "1.00"
        }
      ],
      "unit_price": [
        {
          "number": "52.00",
          "currency_code": "USD"
        }
      ],
      "total_price": [
        {
          "number": "52.00",
          "currency_code": "USD"
        }
      ]
    }
  ]
}

```

## POST

* POST `/carts/{commerce_order}/items?_format=json`

## PATCH

* PATCH `/carts/{commerce_order}/items?_format=json`

## DELETE

* DELETE `/carts/{commerce_order}/items?_format=json`

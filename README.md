# Commerce Cart API

Sandbox to provide a RESTful interface to interact with carts in Drupal Commerce via a lightweight public API.

Example response using JSON API

We need to investigate how we can

* Harness JSON API but use a ResourceType that has dynamic bundle support
* Use fancy Serializer/Normalizer with JsonApiDocumentTopLevel but dynamic bundles
* Support ResourceType's having hardcoded `?include`
* Support ResourceType's having hardcoded `fields`

See routing.yml and controller comments.

```json
{
  "data": {
    "type": "commerce_order--default",
    "id": "8934149f-642a-48d4-b0de-37b9cdd5e2b3",
    "attributes": {
      "uuid": "8934149f-642a-48d4-b0de-37b9cdd5e2b3",
      "adjustments": null,
      "total_price": {
        "number": "89.500000",
        "currency_code": "USD"
      }
    },
    "relationships": {
      "order_items": {
        "data": [
          {
            "type": "commerce_order_item--default",
            "id": "4467e006-0d8c-4d75-a8a8-d2fe32820f2b"
          }
        ],
        "links": {
          "self": "http:\/\/localhost:32781\/jsonapi\/commerce_order\/default\/8934149f-642a-48d4-b0de-37b9cdd5e2b3\/relationships\/order_items",
          "related": "http:\/\/localhost:32781\/jsonapi\/commerce_order\/default\/8934149f-642a-48d4-b0de-37b9cdd5e2b3\/order_items"
        }
      }
    },
    "links": {
      "self": "http:\/\/localhost:32781\/jsonapi\/commerce_order\/default\/8934149f-642a-48d4-b0de-37b9cdd5e2b3"
    }
  },
  "jsonapi": {
    "version": "1.0",
    "meta": {
      "links": {
        "self": "http:\/\/jsonapi.org\/format\/1.0\/"
      }
    }
  },
  "links": {
    "self": "http:\/\/localhost:32781\/cart\/8934149f-642a-48d4-b0de-37b9cdd5e2b3?_format=json\u0026include=order_items\u0026fields%5Bcommerce_order--default%5D=uuid%2Cadjustments%2Ctotal_price%2Corder_items\u0026fields%5Bcommerce_order_item--default%5D=uuid%2Cadjustments%2Cunit_price%2Ctotal_price%2Cquantity%2Ctitle%2Cpurchased_entity"
  },
  "included": [
    {
      "type": "commerce_order_item--default",
      "id": "4467e006-0d8c-4d75-a8a8-d2fe32820f2b",
      "attributes": {
        "uuid": "4467e006-0d8c-4d75-a8a8-d2fe32820f2b",
        "title": "Commerce Guys Hoodie - Cyan,  Small",
        "quantity": "1.00",
        "unit_price": {
          "number": "89.500000",
          "currency_code": "USD"
        },
        "adjustments": null,
        "total_price": {
          "number": "89.500000",
          "currency_code": "USD"
        }
      },
      "relationships": {
        "purchased_entity": {
          "data": {
            "type": "commerce_product_variation--t_shirt",
            "id": "a42be4ee-c332-4a7b-8361-ff6826150d66"
          },
          "links": {
            "self": "http:\/\/localhost:32781\/jsonapi\/commerce_order_item\/default\/4467e006-0d8c-4d75-a8a8-d2fe32820f2b\/relationships\/purchased_entity",
            "related": "http:\/\/localhost:32781\/jsonapi\/commerce_order_item\/default\/4467e006-0d8c-4d75-a8a8-d2fe32820f2b\/purchased_entity"
          }
        }
      },
      "links": {
        "self": "http:\/\/localhost:32781\/jsonapi\/commerce_order_item\/default\/4467e006-0d8c-4d75-a8a8-d2fe32820f2b"
      }
    }
  ]
}
```

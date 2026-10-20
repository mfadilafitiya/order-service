from datetime import datetime, timezone
from pymongo.errors import DuplicateKeyError
from bson import ObjectId
from bson.errors import InvalidId

from app.clients.product_client import product_exists
from app.exceptions import (
    DuplicateReviewError,
    ProductNotFoundError,
    ReviewNotFoundError,
)
from app.models import review_model
from app.schemas.review_schema import validate_create_review, validate_update_review

def _to_object_id(review_id):
    try:
        return ObjectId(review_id)
    except (InvalidId, TypeError) as err:
        raise ReviewNotFoundError(review_id) from err

def get_reviews_for_product(product_id):
    if not product_exists(product_id):
        raise ProductNotFoundError(product_id)
    
    reviews = review_model.find_by_product(product_id)
    summary = review_model.rating_summary(product_id)
    return {
        "product_id": product_id,
        "summary": summary,
        "reviews": reviews,
    }

# --- Tambahan Fungsi untuk Get Review by ID ---
def get_review_by_id(review_id):
    obj_id = _to_object_id(review_id)
    review = review_model.find_by_id(obj_id)
    if not review:
        raise ReviewNotFoundError(review_id)
    return review
# ---------------------------------------------

def create_review(payload):
    data = validate_create_review(payload)
    product_id = data["product_id"]

    if not product_exists(product_id):
        raise ProductNotFoundError(product_id)

    now = datetime.now(timezone.utc)
    document = {
        "product_id": product_id,
        "user_id": data["user_id"],
        "rating": data["rating"],
        "comment": data["comment"],
        "created_at": now,
        "updated_at": now,
    }

    try:
        return review_model.insert(document)
    except DuplicateKeyError as err:
        raise DuplicateReviewError(data["user_id"], product_id) from err

def update_review(review_id, payload):
    obj_id = _to_object_id(review_id)
    existing = review_model.find_by_id(obj_id)
    if not existing:
        raise ReviewNotFoundError(review_id)

    data = validate_update_review(payload)
    data["updated_at"] = datetime.now(timezone.utc)

    updated = review_model.update(obj_id, data)
    if not updated:
        raise ReviewNotFoundError(review_id)
    return updated

def delete_review(review_id):
    obj_id = _to_object_id(review_id)
    success = review_model.delete(obj_id)
    if not success:
        raise ReviewNotFoundError(review_id)
    return True
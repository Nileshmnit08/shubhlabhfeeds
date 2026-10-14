-- =============================================================================
-- Migration 002 Rollback: Clear Seeded Data
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM navigation_items;
DELETE FROM knowledge_articles;
DELETE FROM products;
DELETE FROM product_categories;
DELETE FROM site_sections;
DELETE FROM site_settings;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Migration 002: Seed Initial Verified Baseline Content
-- Populates site settings, sections, products, categories, articles, and navigation
-- =============================================================================

-- 1. Seed Site Settings
INSERT INTO site_settings (setting_key, setting_value, setting_group, is_public, is_system_locked, description) VALUES
('brand_name', 'SHUBH LABH', 'general', 1, 0, 'Official brand name'),
('brand_tagline', 'DAIRY NUTRITION', 'general', 1, 0, 'Brand tagline'),
('announcement_text', 'Nutrition made with farmers in mind ✳ Serving dairy ambitions across Rajasthan', 'general', 1, 0, 'Top announcement banner text'),
('announcement_active', '1', 'general', 1, 0, 'Announcement banner visibility toggle (1/0)'),
('crm_login_url', 'https://shubhlabh-crm.vercel.app/', 'integration', 1, 1, 'Immutable external CRM destination URL'),
('phone_number', NULL, 'contact', 1, 0, 'Official customer helpline (Unconfigured)'),
('whatsapp_number', NULL, 'contact', 1, 0, 'WhatsApp Business phone number (Unconfigured)'),
('public_email', NULL, 'contact', 1, 0, 'Public contact email (Unconfigured)'),
('postal_address', NULL, 'contact', 1, 0, 'Registered factory / office address (Unconfigured)'),
('meta_title', 'Shubh Labh | Better Nutrition. Stronger Dairy.', 'seo', 1, 0, 'Default page meta title'),
('meta_description', 'Shubh Labh — cattle feed and dairy nutrition solutions built for cattle well-being and dairy farmer progress across Rajasthan.', 'seo', 1, 0, 'Default page meta description'),
('footer_text', 'Shubh Labh is committed to farmer-first dairy nutrition, balanced cattle feed formulation, and consistent quality across Rajasthan.', 'general', 1, 0, 'Footer corporate narrative');

-- 2. Seed Site Sections
INSERT INTO site_sections (section_key, title, subtitle, eyebrow, lead_text, body_text, primary_cta_text, primary_cta_url, secondary_cta_text, secondary_cta_url, additional_data, is_published) VALUES
('hero', 'Better nutrition. Stronger dairy.', 'Thoughtful cattle nutrition for the people who keep India''s dairy story moving forward.', 'A partner in dairy progress', NULL, NULL, 'Explore our products ↗', '#products', 'Discover Shubh Labh →', '#about', '{"kicker": "NOURISH THE HERD. NURTURE THE FUTURE.", "indicator": "01 / 03 • Nutrition • Care • Progress"}', 1),
('trust_strip', 'Core Guiding Pillars', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '["Farmer-first thinking", "Nutrition-led approach", "Quality-minded processes", "Growing together"]', 1),
('about', 'For every farmer''s effort, there is a bigger dream.', 'Rooted in trust. Focused on progress.', 'Welcome to Shubh Labh', 'Dairy farming is built on daily dedication. We bring thoughtful nutrition, balanced formulation, and honest quality to every bag.', 'Shubh Labh brings together cattle-feed products designed to support animal wellness, milk productivity, and herd longevity. Rooted in farmer trust and focused on sustainable dairy progress.', NULL, NULL, NULL, NULL, '{"pillars": [{"title": "Farmer at the centre", "desc": "Every feed formulation starts from the practical realities of daily dairy management."}, {"title": "Quality with care", "desc": "Clean raw materials, balanced nutritional ratios, and strict batch hygiene."}, {"title": "Progress together", "desc": "Partnering with dairy families and rural distributors for lasting growth."}]}', 1),
('dealer', 'Let''s take dairy nutrition further.', 'Partnering with progressive rural entrepreneurs across Rajasthan.', 'Become a Dealer', NULL, 'We partner with passionate distributors and rural dealers who believe in farmer-first values, transparent quality, and steady business growth.', 'Enquire about dealership ↗', '#contact', NULL, NULL, '{"benefits": ["Consistent commercial margins and dependable dispatch", "Farmer-tested feed formulations with strong field acceptance", "Dedicated marketing support and nutritional advisory guidance"]}', 1);

-- 3. Seed Product Categories
INSERT INTO product_categories (id, name, slug, category_badge, display_order) VALUES
(1, 'Cattle Feed', 'cattle-feed', 'CATTLE FEED', 1),
(2, 'Nutrition Support', 'nutrition-support', 'NUTRITION SUPPORT', 2);

-- 4. Seed Products (Verified baseline, optional nutritional specs set to NULL)
INSERT INTO products (id, category_id, name, slug, short_description, full_description, packaging_desc, display_order, status) VALUES
(1, 1, 'Shubh Labh Dairy Special', 'dairy-special', 'Balanced dairy ration crafted to support milk yield, digestion, and daily body condition in milking cows and buffaloes.', NULL, 'Available in 50 kg moisture-resistant woven bags', 1, 'published'),
(2, 2, 'Performance Premix', 'performance-premix', 'Nutritional formulation built for balanced energy, mineral support, and consistent dairy performance.', NULL, 'Available in 25 kg & 50 kg packs', 2, 'published'),
(3, 2, 'Specialised Nutrition', 'specialised-nutrition', 'Targeted feeding solutions for transition phases, dry cows, and growing calves.', NULL, 'Available in 25 kg bags', 3, 'published');

-- 5. Seed Knowledge Articles
INSERT INTO knowledge_articles (id, title, slug, category, summary, content_body, display_order, status, published_at) VALUES
(1, 'Essential Feeding Practices for Peak Milk Production', 'feeding-practices-peak-production', 'Dairy Nutrition', 'Practical guidance on dry matter intake, green fodder proportion, and clean drinking water for lactating animals.', '<p>A balanced ration provides energy, protein, fiber, minerals, and vitamins in correct proportion. Ensuring ad-libitum clean drinking water and consistent daily feeding schedules enhances rumination and lactation stability.</p>', 1, 'published', NOW()),
(2, 'Seasonal Dairy Care & Transition Management', 'seasonal-dairy-care', 'Dairy Care', 'Supporting cow health and metabolic balance through weather shifts and calving cycles.', '<p>Managing heat stress during peak summers and providing shelter during chilly winter nights preserves lactation persistence and prevents metabolic disorders during transition phases.</p>', 2, 'published', NOW());

-- 6. Seed Navigation Items
INSERT INTO navigation_items (location, label, url, target, display_order, is_active) VALUES
('header', 'Home', '#home', '_self', 1, 1),
('header', 'Products', '#products', '_self', 2, 1),
('header', 'About', '#about', '_self', 3, 1),
('header', 'Knowledge', '#knowledge', '_self', 4, 1),
('header', 'Become a Dealer', '#dealer', '_self', 5, 1),
('header', 'Contact', '#contact', '_self', 6, 1);

-- Catalogo inicial de demostracion. Es seguro volver a ejecutarlo.
INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
SELECT c.id_categoria, 'Hamburguesa Clásica', 'Carne a la plancha, queso, lechuga, tomate y salsa de la casa.', 28.00, NULL, 1
FROM categorias c
WHERE c.nombre = 'Hamburguesas'
  AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.id_categoria = c.id_categoria AND p.nombre = 'Hamburguesa Clásica');

INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
SELECT c.id_categoria, 'Pizza Personal Pepperoni', 'Pizza individual con salsa de tomate, mozzarella y pepperoni.', 38.00, NULL, 1
FROM categorias c
WHERE c.nombre = 'Pizzas'
  AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.id_categoria = c.id_categoria AND p.nombre = 'Pizza Personal Pepperoni');

INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
SELECT c.id_categoria, 'Salchipapa Especial', 'Papas crocantes, salchicha, queso rallado y salsas.', 30.00, NULL, 1
FROM categorias c
WHERE c.nombre = 'Salchipapas'
  AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.id_categoria = c.id_categoria AND p.nombre = 'Salchipapa Especial');

INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
SELECT c.id_categoria, 'Pollo Crispy (2 piezas)', 'Dos piezas de pollo crujiente con papas fritas.', 35.00, NULL, 1
FROM categorias c
WHERE c.nombre = 'Pollo frito'
  AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.id_categoria = c.id_categoria AND p.nombre = 'Pollo Crispy (2 piezas)');

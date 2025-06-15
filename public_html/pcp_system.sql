-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 15/06/2025 às 03:39
-- Versão do servidor: 10.11.11-MariaDB-ubu2204
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `pcp_system`
--

DELIMITER $$
--
-- Funções
--
CREATE DEFINER=`root`@`localhost` FUNCTION `calcularVolume` (`qtd` DECIMAL(10,2), `espessura` DECIMAL(10,2), `largura` DECIMAL(10,2), `comprimento` DECIMAL(10,2)) RETURNS DECIMAL(12,6) DETERMINISTIC BEGIN
    -- Retorna o resultado do cálculo de volume em metros cúbicos
    -- Retorna 0 se alguma das dimensões for nula ou zero para evitar erros
    IF qtd IS NULL OR espessura IS NULL OR largura IS NULL OR comprimento IS NULL OR qtd = 0 OR espessura = 0 OR largura = 0 OR comprimento = 0 THEN
        RETURN 0;
    ELSE
        RETURN (qtd * espessura * largura * comprimento) / 1000000000;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `acabamentos_lookup`
--

CREATE TABLE `acabamentos_lookup` (
  `id` int(11) NOT NULL,
  `nome` varchar(30) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `acabamentos_lookup`
--

INSERT INTO `acabamentos_lookup` (`id`, `nome`, `descricao`) VALUES
(1, 'Bruto', NULL),
(16, 'Aplainado', NULL),
(24, 'Acabado', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `apontamentos_producao`
--

CREATE TABLE `apontamentos_producao` (
  `id` int(11) NOT NULL,
  `ordem_producao_id` int(11) NOT NULL,
  `maquina_id` int(11) NOT NULL,
  `operador_id` int(11) DEFAULT NULL,
  `quantidade_produzida` decimal(10,2) NOT NULL,
  `data_apontamento` datetime NOT NULL DEFAULT current_timestamp(),
  `data_hora_apontamento` datetime NOT NULL DEFAULT current_timestamp(),
  `observacoes` text DEFAULT NULL,
  `lote_numero` varchar(150) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `apontamentos_producao`
--

INSERT INTO `apontamentos_producao` (`id`, `ordem_producao_id`, `maquina_id`, `operador_id`, `quantidade_produzida`, `data_apontamento`, `data_hora_apontamento`, `observacoes`, `lote_numero`, `deleted_at`) VALUES
(2, 1, 2, 1, 260.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '1', '250001-2', '2025-05-31 00:00:00'),
(3, 1, 2, 1, 260.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '1', '250001-3', '2025-05-31 00:00:00'),
(4, 1, 2, 1, 260.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', 'a', '250001-4', '2025-05-31 00:00:00'),
(5, 1, 2, 1, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '2', '250001-5', '2025-05-31 00:00:00'),
(9, 2, 11, 2, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '50', '2506757719-9', '2025-05-31 00:00:00'),
(10, 1, 11, 1, 900.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '910', '250001-10', '2025-05-31 00:00:00'),
(11, 2, 11, 5, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '50', '2506757719-11', '2025-05-31 00:00:00'),
(12, 2, 11, 5, 90.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '90', '2506757719-12', '2025-05-31 00:00:00'),
(15, 2, 11, 5, 20.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '10', '2506757719-15', '2025-05-31 00:00:00'),
(27, 8, 5, 5, 99.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506521602-27', '2025-05-31 00:00:00'),
(28, 12, 5, 5, 36.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506607049-28', '2025-05-31 00:00:00'),
(36, 3, 5, 5, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506354138-36', '2025-05-31 00:00:00'),
(37, 3, 5, 5, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506354138-37', '2025-05-31 00:00:00'),
(38, 2, 11, 5, 99.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506757719-38', '2025-05-31 00:00:00'),
(39, 15, 5, 5, 99.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', 'Teste', '2506855997-39', '2025-05-31 00:00:00'),
(40, 17, 11, 5, 99.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', 'Teste', '2506659847-40', '2025-05-31 00:00:00'),
(41, 15, 5, 5, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506855997-41', '2025-05-31 00:00:00'),
(42, 6, 2, 5, 120.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506119190-42', '2025-05-31 00:00:00'),
(43, 15, 5, 5, 100.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506855997-43', '2025-05-31 00:00:00'),
(44, 18, 5, 4, 100.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506102927-44', '2025-05-31 00:00:00'),
(45, 18, 5, 5, 1900.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506102927-45', '2025-05-31 00:00:00'),
(46, 19, 5, 5, 100.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506096008-46', '2025-05-31 00:00:00'),
(47, 19, 5, 5, 250.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506096008-47', '2025-05-31 00:00:00'),
(48, 22, 9, 5, 2200.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506353264-48', '2025-05-31 00:00:00'),
(49, 24, 11, 5, 1400.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506595644-49', '2025-05-31 00:00:00'),
(50, 24, 11, 5, 1400.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506595644-50', '2025-05-31 00:00:00'),
(51, 23, 11, 5, 2100.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506868668-51', '2025-05-31 00:00:00'),
(52, 19, 5, 5, 500.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506096008-52', '2025-05-31 00:00:00'),
(53, 16, 2, 5, 1.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506356118-53', '2025-05-31 00:00:00'),
(54, 16, 2, 1, 1.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506356118-54', '2025-05-31 00:00:00'),
(55, 16, 2, 3, 1.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506356118-55', '2025-05-31 00:00:00'),
(56, 16, 2, 4, 99.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506356118-56', '2025-05-31 00:00:00'),
(57, 26, 5, 5, 2500.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506218500-57', '2025-05-31 00:00:00'),
(58, 27, 11, 5, 5000.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506862308-58', '2025-05-31 00:00:00'),
(59, 27, 11, 5, 5000.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506862308-59', '2025-05-31 00:00:00'),
(60, 26, 5, 5, 2500.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506218500-60', '2025-05-31 00:00:00'),
(61, 29, 11, 5, 30.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506037382-61', '2025-05-31 00:00:00'),
(62, 30, 5, 5, 1000.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506967993-62', '2025-05-31 00:00:00'),
(63, 29, 11, 5, 31.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506037382-63', '2025-05-31 00:00:00'),
(64, 29, 11, 5, 55.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506037382-64', '2025-05-31 00:00:00'),
(65, 31, 5, 5, 1500.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506517118-65', '2025-05-31 00:00:00'),
(66, 29, 11, 2, 200.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506037382-66', '2025-05-31 00:00:00'),
(67, 32, 5, 4, 1.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506704381-67', '2025-05-31 00:00:00'),
(68, 32, 5, 5, 1400.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506704381-68', '2025-05-31 00:00:00'),
(69, 29, 11, 5, 19.50, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506037382-69', '2025-05-31 00:00:00'),
(70, 29, 11, 1, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506037382-70', '2025-05-31 00:00:00'),
(71, 29, 11, 4, 100.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506037382-71', '2025-05-31 00:00:00'),
(72, 32, 5, 5, 10.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506704381-72', '2025-05-31 00:00:00'),
(73, 33, 5, 5, 333.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506363416-73', '2025-05-31 00:00:00'),
(74, 33, 5, 4, 222.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506363416-74', '2025-05-31 00:00:00'),
(75, 35, 5, 5, 750.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506156257-75', '2025-05-31 00:00:00'),
(76, 35, 5, 4, 1000.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506156257-76', '2025-05-31 00:00:00'),
(78, 36, 5, 5, 500.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506189834-78', '2025-05-31 00:00:00'),
(79, 36, 5, 5, 250.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506189834-79', '2025-05-31 00:00:00'),
(80, 38, 5, 5, 500.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506401132-80', '2025-05-31 00:00:00'),
(81, 38, 5, 5, 250.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506401132-81', '2025-05-31 00:00:00'),
(82, 38, 5, 5, 250.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506401132-82', '2025-05-31 00:00:00'),
(83, 38, 5, 5, 500.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', 'Cc', '2506401132-83', '2025-05-31 00:00:00'),
(85, 40, 13, 5, 8000.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506555475-85', '2025-05-31 00:00:00'),
(88, 38, 5, 1, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506401132-88', '2025-05-31 00:00:00'),
(94, 38, 5, 5, 50.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506401132-94', '2025-05-31 00:00:00'),
(105, 38, 5, 4, 9.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506401132-105', '2025-05-31 00:00:00'),
(106, 38, 5, 1, 445.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '445', '2506401132-106', '2025-05-31 00:00:00'),
(107, 38, 5, 5, 446.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506401132-107', '2025-05-31 00:00:00'),
(108, 44, 11, 5, 100.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506637402-108', '2025-05-31 00:00:00'),
(109, 44, 11, 5, 500.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506637402-109', '2025-05-31 00:00:00'),
(110, 44, 11, 5, 399.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506637402-110', '2025-05-31 00:00:00'),
(111, 44, 11, 5, 2001.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506637402-111', '2025-05-31 00:00:00'),
(112, 45, 11, 5, 3200.00, '2025-05-30 00:00:00', '2025-05-30 00:00:00', '', '2506874801-112', '2025-05-31 00:00:00'),
(113, 48, 9, 5, 20.00, '2025-06-16 16:03:00', '2025-06-14 16:04:15', '', '2506872118-113', '2025-06-14 16:15:56'),
(114, 48, 9, 5, 15.00, '2025-06-17 16:04:00', '2025-06-14 16:04:34', '', '2506872118-114', '2025-06-14 16:15:53'),
(115, 57, 9, 5, 13000.00, '2025-06-16 17:31:00', '2025-06-14 17:31:31', '', '2506736708-115', '2025-06-14 17:43:42'),
(116, 57, 9, 5, 13500.00, '2025-06-17 17:31:00', '2025-06-14 17:31:45', '', '2506736708-116', '2025-06-14 17:43:45'),
(117, 57, 9, 5, 1164.00, '2025-06-18 17:31:00', '2025-06-14 17:34:05', '', '2506736708-117', '2025-06-14 17:44:13'),
(118, 60, 9, 5, 15561.00, '2025-06-16 18:07:00', '2025-06-14 18:08:05', '', '2506148450-118', '2025-06-14 18:23:48'),
(119, 60, 9, 5, 15561.00, '2025-06-16 19:13:00', '2025-06-14 19:13:40', '', '2506148450-119', '2025-06-15 00:07:38'),
(120, 60, 9, 5, 15561.00, '2025-06-17 19:13:00', '2025-06-14 19:14:36', '', '2506148450-120', '2025-06-15 00:08:19'),
(121, 59, 9, 5, 12332.00, '2025-06-14 19:29:00', '2025-06-14 19:30:02', '', '2506473569-121', '2025-06-14 19:30:51'),
(122, 59, 9, 5, 13832.00, '2025-06-14 19:30:00', '2025-06-14 19:42:49', '', '2506473569-122', '2025-06-14 19:46:05'),
(123, 59, 9, 5, 13832.00, '2025-06-14 19:42:00', '2025-06-14 19:44:32', '', '2506473569-123', '2025-06-14 19:47:24'),
(129, 61, 9, 5, 18832.00, '2025-06-18 20:37:00', '2025-06-14 20:37:41', '', '2506960359-129', '2025-06-14 22:35:44'),
(130, 61, 9, 5, 18832.00, '2025-06-14 20:37:00', '2025-06-14 20:38:23', '', '2506960359-130', '2025-06-14 23:15:59'),
(133, 54, 11, 4, 1.00, '2025-06-14 20:52:00', '2025-06-14 20:52:16', '', '2506480978-133', '2025-06-14 20:52:45'),
(134, 53, 4, 1, 10400.00, '2025-06-14 20:54:00', '2025-06-14 20:54:34', '', '2506747004-134', '2025-06-14 20:56:22'),
(135, 53, 4, 4, 5.00, '2025-06-14 20:54:00', '2025-06-14 20:55:00', '', '2506747004-135', '2025-06-14 20:55:56'),
(136, 53, 4, 1, 10400.00, '2025-06-14 20:56:00', '2025-06-14 20:56:57', '', '2506747004-136', '2025-06-14 20:57:09'),
(137, 53, 4, 5, 10400.00, '2025-06-14 21:05:00', '2025-06-14 21:05:29', '', '2506747004-137', '2025-06-14 21:09:07'),
(138, 53, 4, 1, 10000.00, '2025-06-14 21:06:00', '2025-06-14 21:06:14', '', '2506747004-138', '2025-06-14 21:06:32'),
(139, 53, 4, 5, 10400.00, '2025-06-14 21:10:00', '2025-06-14 21:10:39', '', '2506747004-139', '2025-06-14 21:10:51'),
(141, 53, 4, 4, 10400.00, '2025-06-14 21:18:00', '2025-06-14 21:18:32', '', '2506747004-141', '2025-06-14 21:19:15'),
(142, 53, 4, 5, 10400.00, '2025-06-14 21:19:00', '2025-06-14 21:20:14', '', '2506747004-142', '2025-06-14 21:20:35'),
(143, 53, 4, 2, 10400.00, '2025-06-14 21:23:00', '2025-06-14 21:24:26', '', '2506747004-143', '2025-06-14 21:24:50'),
(147, 53, 4, 5, 10400.00, '2025-06-14 21:34:00', '2025-06-14 21:34:56', '', '2506747004-147', '2025-06-15 00:22:26'),
(148, 53, 4, 4, 10400.00, '2025-06-18 21:34:00', '2025-06-14 21:35:09', '', '2506747004-148', '2025-06-15 00:22:35'),
(153, 61, 9, 5, 8832.00, '2025-06-14 23:15:59', '2025-06-14 23:15:59', 'devolucao do lote 2506960359-130', '2506960359-DEV130', '2025-06-14 23:17:37'),
(155, 54, 11, 5, 23000.00, '2025-06-15 00:01:00', '2025-06-15 00:02:35', '', '2506480978-155', '2025-06-15 00:19:36'),
(156, 54, 11, 5, 400.00, '2025-06-15 00:08:00', '2025-06-15 00:09:02', '', '2506480978-156', NULL),
(158, 51, 5, 5, 800.00, '2025-06-15 00:14:00', '2025-06-15 00:17:26', '', '2506269286-158', NULL),
(159, 51, 5, 5, 800.00, '2025-06-15 00:17:00', '2025-06-15 00:17:47', '', '2506269286-159', NULL),
(160, 51, 5, 5, 990.00, '2025-06-16 00:23:00', '2025-06-15 00:23:22', '', '2506269286-160', NULL),
(161, 51, 5, 5, 10.00, '2025-06-16 00:24:00', '2025-06-15 00:24:48', '', '2506269286-161', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `consumo_producao`
--

CREATE TABLE `consumo_producao` (
  `id` int(11) NOT NULL,
  `apontamento_id` int(11) DEFAULT NULL,
  `ordem_producao_id` int(11) NOT NULL,
  `produto_material_id` int(11) NOT NULL,
  `quantidade_consumida` decimal(10,4) NOT NULL,
  `data_consumo` datetime NOT NULL DEFAULT current_timestamp(),
  `responsavel_id` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `consumo_producao`
--

INSERT INTO `consumo_producao` (`id`, `apontamento_id`, `ordem_producao_id`, `produto_material_id`, `quantidade_consumida`, `data_consumo`, `responsavel_id`, `observacoes`, `deleted_at`) VALUES
(8, 94, 38, 45, 50.0000, '2025-05-30 00:00:00', 5, NULL, '2025-05-31 00:00:00'),
(13, 80, 38, 45, 500.0000, '2025-05-30 00:00:00', 3, NULL, '2025-05-31 00:00:00'),
(14, 81, 38, 45, 250.0000, '2025-05-30 00:00:00', 5, NULL, '2025-05-31 00:00:00'),
(15, 105, 38, 45, 9.0000, '2025-05-30 00:00:00', 4, NULL, '2025-05-31 00:00:00'),
(16, 83, 38, 45, 500.0000, '2025-05-30 00:00:00', 5, NULL, '2025-05-31 00:00:00'),
(17, NULL, 38, 47, 1000.0000, '2025-05-30 00:00:00', 5, 'Consumo de insumo direto para a OP.', '2025-05-31 00:00:00'),
(18, NULL, 38, 47, 10000.0000, '2025-05-30 00:00:00', 5, 'Consumo de insumo direto para a OP.', '2025-05-31 00:00:00'),
(19, NULL, 38, 47, 18480.0000, '2025-05-30 00:00:00', 1, 'Consumo de insumo direto para a OP.', '2025-05-31 00:00:00'),
(20, NULL, 38, 47, 18480.0000, '2025-05-30 00:00:00', 4, 'Consumo de insumo direto para a OP.', '2025-05-31 00:00:00'),
(22, 147, 53, 14, 10400.0000, '2025-06-15 00:50:00', 4, NULL, '2025-06-14 21:52:58'),
(24, 129, 53, 4, 18832.0000, '2025-06-15 01:20:00', 5, NULL, NULL),
(30, 130, 53, 4, 10000.0000, '2025-06-15 02:14:00', 5, NULL, NULL),
(32, 153, 53, 4, 8832.0000, '2025-06-15 02:17:00', 5, NULL, NULL),
(33, NULL, 61, 43, 100.0000, '2025-06-15 02:50:00', 5, 'Consumo de matéria-prima direto para a OP.', '2025-06-14 23:51:43'),
(34, NULL, 61, 43, 100.0000, '2025-06-15 02:51:00', 1, 'Consumo de matéria-prima direto para a OP.', '2025-06-14 23:56:10'),
(35, NULL, 61, 43, 100.0000, '2025-06-15 02:55:00', 5, 'Consumo de matéria-prima direto para a OP.', NULL),
(36, NULL, 61, 43, 20.0000, '2025-06-15 02:56:00', 5, 'Consumo de matéria-prima direto para a OP.', NULL),
(37, NULL, 60, 44, 1500.0000, '2025-06-15 02:57:00', 5, 'Consumo de matéria-prima direto para a OP.', NULL),
(38, 119, 54, 1, 15561.0000, '2025-06-15 03:06:00', 5, NULL, NULL),
(39, 120, 54, 1, 15561.0000, '2025-06-15 03:07:00', 5, NULL, NULL),
(40, 155, 51, 46, 23000.0000, '2025-06-15 03:18:00', 5, NULL, NULL),
(41, 147, 51, 14, 10400.0000, '2025-06-15 03:19:00', 5, NULL, NULL),
(42, 148, 51, 14, 10400.0000, '2025-06-15 03:22:00', 5, NULL, NULL),
(43, NULL, 51, 47, 650.0000, '2025-06-15 03:23:00', 5, 'Consumo de matéria-prima direto para a OP.', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `empenho_materiais`
--

CREATE TABLE `empenho_materiais` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `ordem_producao_id` int(11) NOT NULL,
  `quantidade_empenhada` decimal(10,2) NOT NULL,
  `quantidade_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `data_empenho` datetime NOT NULL DEFAULT current_timestamp(),
  `observacoes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `empenho_materiais`
--

INSERT INTO `empenho_materiais` (`id`, `produto_id`, `ordem_producao_id`, `quantidade_empenhada`, `quantidade_inicial`, `data_empenho`, `observacoes`, `deleted_at`) VALUES
(13, 14, 25, 8500.00, 0.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506725474', '2025-05-31 00:00:00'),
(15, 47, 25, 80000.00, 0.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506725474', '2025-05-31 00:00:00'),
(16, 14, 26, 0.00, 0.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506218500', '2025-05-31 00:00:00'),
(18, 47, 26, 0.00, 0.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506218500', '2025-05-31 00:00:00'),
(19, 36, 28, 70.00, 0.00, '2025-05-30 00:00:00', '', '2025-05-31 00:00:00'),
(20, 43, 29, 0.00, 800.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506037382', '2025-05-30 00:28:22'),
(21, 14, 30, 9350.00, 9350.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506967993', '2025-05-31 00:00:00'),
(23, 47, 30, 88000.00, 88000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506967993', '2025-05-31 00:00:00'),
(25, 14, 31, 51000.00, 25500.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506517118', '2025-05-31 00:00:00'),
(27, 47, 31, 480000.00, 240000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506517118', '2025-05-31 00:00:00'),
(28, 14, 32, 11900.00, 23800.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506704381', '2025-05-31 00:00:00'),
(29, 46, 32, 12600.00, 25200.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506704381', '2025-05-31 00:00:00'),
(30, 47, 32, 112000.00, 224000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506704381', '2025-05-31 00:00:00'),
(37, 14, 33, 2830.50, 2830.50, '2025-05-30 00:00:00', 'Empenho automático para OP 2506363416', '2025-05-31 00:00:00'),
(38, 46, 33, 2997.00, 2997.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506363416', '2025-05-31 00:00:00'),
(39, 47, 33, 26640.00, 26640.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506363416', '2025-05-31 00:00:00'),
(40, 14, 34, 2830.50, 2830.50, '2025-05-30 00:00:00', 'Empenho automático para OP 2506874303', '2025-05-31 00:00:00'),
(41, 46, 34, 2997.00, 2997.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506874303', '2025-05-31 00:00:00'),
(42, 47, 34, 26640.00, 26640.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506874303', '2025-05-31 00:00:00'),
(46, 14, 35, 17000.00, 17000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506156257', '2025-05-31 00:00:00'),
(47, 46, 35, 18000.00, 18000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506156257', '2025-05-31 00:00:00'),
(48, 47, 35, 160000.00, 160000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506156257', '2025-05-31 00:00:00'),
(51, 36, 35, 50.00, 50.00, '2025-05-30 00:00:00', 'OP 2506156257', '2025-05-31 00:00:00'),
(52, 14, 36, 0.00, 6800.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506189834', '2025-05-31 00:00:00'),
(53, 46, 36, 0.00, 7200.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506189834', '2025-05-31 00:00:00'),
(54, 47, 36, 0.00, 64000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506189834', '2025-05-31 00:00:00'),
(55, 14, 37, 21250.00, 21250.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506994029', '2025-05-31 00:00:00'),
(56, 46, 37, 22500.00, 22500.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506994029', '2025-05-31 00:00:00'),
(57, 47, 37, 200000.00, 200000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506994029', '2025-05-31 00:00:00'),
(58, 14, 38, 0.00, 21250.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506401132', '2025-05-31 00:00:00'),
(59, 46, 38, 0.00, 22500.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506401132', '2025-05-31 00:00:00'),
(60, 47, 38, 0.00, 200000.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506401132', '2025-05-31 00:00:00'),
(61, 4, 39, 0.00, 19950.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506943935', '2025-05-31 00:00:00'),
(62, 44, 40, 1000.00, 1000.00, '2025-05-30 00:00:00', '', '2025-05-31 00:00:00'),
(63, 44, 41, 0.00, 28800.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506083960', '2025-05-31 00:00:00'),
(64, 36, 38, 15.00, 11.00, '2025-05-30 00:00:00', '', '2025-05-31 00:00:00'),
(67, 43, 44, 0.00, 5400.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506637402', '2025-05-31 00:00:00'),
(68, 43, 45, 0.00, 5760.00, '2025-05-30 00:00:00', 'Empenho automático para OP 2506874801', '2025-05-31 00:00:00'),
(69, 14, 46, 28.60, 28.60, '2025-06-14 15:36:36', 'Empenho automático para OP 2506527204', '2025-06-14 16:23:19'),
(70, 46, 46, 23.40, 23.40, '2025-06-14 15:36:36', 'Empenho automático para OP 2506527204', '2025-06-14 16:23:19'),
(71, 47, 46, 780.00, 780.00, '2025-06-14 15:36:36', 'Empenho automático para OP 2506527204', '2025-06-14 16:23:19'),
(72, 1, 47, 33.25, 33.25, '2025-06-14 15:55:13', 'Empenho automático para OP 2506883950', '2025-06-14 16:23:18'),
(73, 44, 48, 0.00, 63.00, '2025-06-14 15:57:35', 'Empenho automático para OP 2506872118', '2025-06-14 16:19:30'),
(74, 4, 49, 39.90, 39.90, '2025-06-14 15:59:55', 'Empenho automático para OP 2506064172', '2025-06-14 16:23:16'),
(75, 44, 50, 64.80, 64.80, '2025-06-14 16:19:13', 'Empenho automático para OP 2506976449', '2025-06-14 16:23:14'),
(76, 14, 51, 0.00, 20800.00, '2025-06-14 16:28:38', 'Empenho automático para OP 2506269286', '2025-06-15 00:24:48'),
(77, 46, 51, 0.00, 23400.00, '2025-06-14 16:28:38', 'Empenho automático para OP 2506269286', '2025-06-15 00:24:48'),
(78, 47, 51, 0.00, 650.00, '2025-06-14 16:28:38', 'Empenho automático para OP 2506269286', '2025-06-15 00:24:48'),
(79, 4, 52, 3724.00, 3724.00, '2025-06-14 17:17:31', 'Empenho automático para OP 2506132206', '2025-06-14 17:19:12'),
(80, 4, 53, 0.00, 27664.00, '2025-06-14 17:20:34', 'Empenho automático para OP 2506747004', '2025-06-14 21:35:09'),
(81, 1, 54, 0.00, 31122.00, '2025-06-14 17:21:57', 'Empenho automático para OP 2506480978', '2025-06-15 00:09:02'),
(82, 43, 55, 49795.20, 49795.20, '2025-06-14 17:23:34', 'Empenho automático para OP 2506638740', '2025-06-14 17:26:10'),
(83, 43, 56, 49795.20, 49795.20, '2025-06-14 17:26:45', 'Empenho automático para OP 2506116740', '2025-06-14 17:28:18'),
(84, 43, 57, 0.00, 74.69, '2025-06-14 17:28:28', 'Empenho automático para OP 2506736708', '2025-06-14 17:44:24'),
(85, 4, 58, 27664.00, 27664.00, '2025-06-14 17:46:13', 'Empenho automático para OP 2506787821', '2025-06-14 17:55:56'),
(86, 43, 59, 0.00, 74.69, '2025-06-14 17:48:00', 'Empenho automático para OP 2506473569', '2025-06-14 20:00:53'),
(87, 44, 60, 0.00, 1456.51, '2025-06-14 17:50:00', 'Empenho automático para OP 2506148450', '2025-06-14 19:13:40'),
(88, 43, 61, 0.00, 101.69, '2025-06-14 20:23:46', 'Empenho automático para OP 2506960359', '2025-06-14 20:38:23');

--
-- Acionadores `empenho_materiais`
--
DELIMITER $$
CREATE TRIGGER `before_empenho_materials_update` BEFORE UPDATE ON `empenho_materiais` FOR EACH ROW BEGIN
    -- Apenas ajusta quantidade_empenhada se quantidade_inicial foi alterada
    IF NEW.quantidade_inicial <> OLD.quantidade_inicial THEN
        -- Calcula a nova quantidade_empenhada (restante) com base na sua fórmula:
        -- QE_nova = (QI_nova - QI_antiga) + QE_atual_passada_ou_existente
        -- NEW.quantidade_empenhada é o valor que o PHP está tentando setar,
        -- ou o valor existente se PHP não setou explicitamente um novo.
        -- Se o PHP está enviando a QE_atual_db (OLD.quantidade_empenhada para o PHP), a fórmula é (NEW.QI - OLD.QI) + OLD.QE
        SET NEW.quantidade_empenhada = OLD.quantidade_empenhada + (NEW.quantidade_inicial - OLD.quantidade_inicial);
        
        -- Garante que a quantidade_empenhada não se torne negativa
        SET NEW.quantidade_empenhada = GREATEST(0, NEW.quantidade_empenhada);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `familias_lookup`
--

CREATE TABLE `familias_lookup` (
  `id` int(11) NOT NULL,
  `nome` varchar(20) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `familias_lookup`
--

INSERT INTO `familias_lookup` (`id`, `nome`, `descricao`) VALUES
(1, 'Palete', NULL),
(9, 'Tabique', NULL),
(18, 'Linha A', 'Primeira linha de produtos.'),
(19, 'Linha B', 'Segunda linha de produtos.'),
(20, 'Linha C', 'Terceira linha de produtos.'),
(21, 'Especiais', 'Produtos com características únicas.'),
(22, 'Componentes', 'Família de componentes.'),
(33, 'Materia-Prima', NULL),
(38, 'PBR', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `fornecedores_clientes_lookup`
--

CREATE TABLE `fornecedores_clientes_lookup` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('fornecedor','cliente','ambos') DEFAULT 'fornecedor',
  `cnpj` varchar(18) DEFAULT NULL,
  `contato` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `fornecedores_clientes_lookup`
--

INSERT INTO `fornecedores_clientes_lookup` (`id`, `nome`, `tipo`, `cnpj`, `contato`, `email`, `telefone`, `endereco`, `observacoes`, `deleted_at`) VALUES
(1, 'ABC Componentes Ltda.', 'fornecedor', '00.123.456/0001-01', 'João Carlos', 'contato@abccomp.com.br', '(11) 98765-4321', 'Rua das Indústrias, 100 - SP', 'Fornecedor principal de eletrônicos.', NULL),
(2, 'Megtalúrgica XYZ S.A.', 'fornecedor', '01.234.567/0001-02', 'Maria Fernanda', 'vendas@metalxyz.com.br', '(21) 97654-3210', 'Av. Metal, 500 - RJ', 'Fornecedor de matéria-prima metálica.', NULL),
(3, 'Plastic Solutions ME', 'fornecedor', '02.345.678/0001-03', 'Pedro Henrique', 'comercial@plasticsol.com.br', '(31) 96543-2109', 'Rua dos Plásticos, 30 - MG', 'Fornecedor de insumos plásticos.', NULL),
(4, 'Global Logistics', 'fornecedor', '03.456.789/0001-04', 'Ana Paula', 'suporte@globallog.com', '(41) 95432-1098', 'Rodovia do Comércio, 2000 - PR', 'Empresa de logística e transporte', NULL),
(5, 'Cliente Final S.A.', 'cliente', NULL, 'Diretoria', 'diretoria@final.com.br', '(81) 94321-0987', 'Rua da Consumação, 1 - PE', 'Cliente principal de produtos acabados.', NULL),
(6, 'Mega Distribuidores', 'ambos', '04.567.890/0001-05', 'Roberta G.', 'info@megadist.com.br', '(51) 93210-9876', 'Av. Distribuição, 123 - RS', 'Fornece e compra produtos esporadicamente.', NULL),
(10, 'Jurandir Monteiro Prestes', 'cliente', '04.289.296/0001-63', 'Jura', 'jurandir@jtekinfo.com.br', '43999302023', 'Acácio de Souza, 276\r\nCasa', NULL, '2025-06-09 01:28:08');

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupos_lookup`
--

CREATE TABLE `grupos_lookup` (
  `id` int(11) NOT NULL,
  `nome` varchar(30) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `grupos_lookup`
--

INSERT INTO `grupos_lookup` (`id`, `nome`, `descricao`) VALUES
(1, 'Serrados', 'Serrados'),
(2, 'Refilados', 'Refilados'),
(15, 'Blocos', NULL),
(46, 'Materiais Brutos', 'Materiais em estado bruto, sem processamento.'),
(47, 'Componentes Eletrônicos', 'Peças e circuitos eletrônicos.'),
(48, 'Peças Usinadas', 'Peças que passaram por processo de usinagem.'),
(49, 'Embalagens', 'Materiais utilizados para empacotar produtos.'),
(61, 'Toras', NULL),
(68, 'Palete', NULL),
(73, 'Pregos', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `lista_materiais`
--

CREATE TABLE `lista_materiais` (
  `id` int(11) NOT NULL,
  `produto_pai_id` int(11) NOT NULL,
  `produto_filho_id` int(11) NOT NULL,
  `quantidade_necessaria` decimal(10,4) NOT NULL,
  `observacoes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `lista_materiais`
--

INSERT INTO `lista_materiais` (`id`, `produto_pai_id`, `produto_filho_id`, `quantidade_necessaria`, `observacoes`, `deleted_at`) VALUES
(4, 14, 4, 1.3300, '0,75', NULL),
(8, 4, 43, 0.0027, '1,8 toneladas para 1 metro cubico', NULL),
(9, 45, 14, 8.0000, '8 ripas', NULL),
(10, 45, 46, 9.0000, '9 blocos', NULL),
(11, 45, 47, 0.2500, '+-80 pregos', NULL),
(12, 46, 44, 1.8000, '1,8 toneladas para 1 metro cubico', '2025-06-14 15:17:13'),
(13, 46, 1, 1.3300, '1 / 0,75', NULL),
(14, 1, 44, 0.0468, '1,8 ton para 1m3', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `localizacoes_lookup`
--

CREATE TABLE `localizacoes_lookup` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `localizacoes_lookup`
--

INSERT INTO `localizacoes_lookup` (`id`, `nome`, `descricao`) VALUES
(1, 'Setor CNC 1', 'Área de máquinas CNC de usinagem.'),
(2, 'Setor Plástico A', 'Área de máquinas injetoras e sopradoras.'),
(3, 'Setor Estampagem', 'Área de prensas e dobradeiras.'),
(4, 'Célula Robótica 1', 'Célula de trabalho com robôs industriais.'),
(5, 'Setor de Corte', 'Área de máquinas de corte a laser e plasma.'),
(6, 'Oficina Mecânica', 'Área para manutenção e pequenos reparos.'),
(11, 'Setor Eletrônico', NULL),
(13, 'Linha de Produção', NULL),
(14, 'Almoxarifado Principal', NULL),
(15, 'Setor de Plásticos', NULL),
(16, 'Patio', NULL),
(20, 'Depósito', NULL),
(22, 'Setor de Expedição', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `maquinas`
--

CREATE TABLE `maquinas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` enum('operacional','manutencao','parada') NOT NULL DEFAULT 'operacional',
  `numero_serie` varchar(50) DEFAULT NULL,
  `tag_ativo` varchar(30) DEFAULT NULL,
  `fabricante` varchar(100) DEFAULT NULL,
  `modelo_maquina` varchar(50) DEFAULT NULL,
  `tipo_maquina` varchar(50) DEFAULT NULL,
  `localizacao` varchar(100) DEFAULT NULL,
  `data_aquisicao` date DEFAULT NULL,
  `data_ultima_manutencao` date DEFAULT NULL,
  `capacidade_hora` decimal(10,2) DEFAULT NULL,
  `unidade_capacidade` varchar(20) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `maquinas`
--

INSERT INTO `maquinas` (`id`, `nome`, `descricao`, `status`, `numero_serie`, `tag_ativo`, `fabricante`, `modelo_maquina`, `tipo_maquina`, `localizacao`, `data_aquisicao`, `data_ultima_manutencao`, `capacidade_hora`, `unidade_capacidade`, `deleted_at`) VALUES
(1, 'Fresa CNC V-200', 'Centro de usinagem CNC de alta precisão para metais.', 'manutencao', 'SN-FRESAV200-001', 'MAQ-001', 'MetalTech', 'V-200 Industrial', 'Usinagem', 'Setor CNC 1', '2020-03-15', '2024-05-10', 15.50, 'Peças/h', NULL),
(2, 'Injetora Plástica IP-500', 'Máquina de injeção de plástico para peças de médio porte.', 'operacional', 'SN-INJETORA-500-A', 'MAQ-002', 'PlastForm', 'IP-500 Deluxe', 'Injeção', 'Setor Plástico A', '2019-08-20', '2024-04-25', 120.00, 'Kg/h', NULL),
(3, 'Prensa Hidráulica PH-30T', 'Prensa de 30 toneladas para estampagem e corte.', 'operacional', 'SN-PRESSA-30T-XYZ', 'MAQ-003', 'HeavyPress', 'PH-30T Compact', 'Conformação', 'Setor Estampagem', '2018-01-10', '2024-06-01', 50.00, 'Ciclos/h', NULL),
(4, 'Refiladeira', 'Robô articulado para soldagem automatizada de precisão.', 'operacional', 'SN-ROBOT-AM-2.1', 'MAQ-004', 'RoboWeld', 'ArcMaster 2.1', 'Soldagem', 'Célula Robótica 1', '2021-11-01', '2024-05-20', 25.00, 'Unidades/h', NULL),
(5, 'Montagem de Palete Manual', 'Montagem manual de palete', 'operacional', 'SN-LASER-L5K-03', 'MAQ-005', 'LaserCut', 'L-5000 Pro', 'Corte', 'Setor de Corte', '2022-04-01', '2024-03-15', 8.00, 'Chapas/h', NULL),
(6, 'Torno Automático TA-100', 'Torno de precisão para peças cilíndricas pequenas.', 'operacional', 'SN-TORNO-A-100', 'MAQ-006', 'PrecisionTools', 'TA-100 Lite', 'Usinagem', 'Setor CNC 2', '2017-09-05', '2024-04-01', 30.00, 'Peças/h', NULL),
(7, 'Máquina de Dobra Hidráulica', 'Dobradeira para chapas de metal, até 2 metros.', 'operacional', 'SN-DOBRA-MH-01', 'MAQ-007', 'BendTech', 'HydroBend 2000', 'Conformação', 'Setor Estampagem', '2016-02-28', '2024-02-10', 10.00, 'Dobras/h', NULL),
(8, 'Montadora Automatizada MA-1', 'Linha de montagem automatizada para componentes eletrônicos.', 'operacional', 'SN-MONT-AUTO-01', 'MAQ-008', 'AutoAssemble', 'MA-1 Smart', 'Montagem', 'Linha Montagem 1', '2023-01-20', '2024-05-05', 500.00, 'Unidades/h', NULL),
(9, 'Serraria', 'Máquina de embalagem para produtos acabados.', 'operacional', 'SN-EMB-VERT-02', 'MAQ-009', 'PackFast', 'VertPack 1.0', 'Embalagem', 'Setor de Expedição', '2021-07-12', '2024-05-22', 150.00, 'Pacotes/h', NULL),
(10, 'Forno de Tratamento Térmico', 'Forno para tratamento térmico de metais.', 'operacional', 'SN-FORNO-TT-05', 'MAQ-010', 'HeatWorks', 'TT-5 Industrial', 'Tratamento', 'Setor Tratamento', '2020-10-01', '2024-04-18', 5.00, 'Batches/h', '2025-06-06 19:19:31'),
(11, 'Corte de Blocos', 'Corte de metais espessos com tecnologia a plasma.', 'operacional', 'SN-PLASMA-MAX', 'MAQ-011', 'PlasmaCut', 'MaxPlasma 300', 'Corte', 'Setor de Corte', '2023-08-01', '2024-05-28', 6.00, 'Peças/h', NULL),
(12, 'Fresadora Manual FM-10', 'Fresadora manual para pequenos ajustes e protótipos.', 'parada', 'SN-FRESAM-010', 'MAQ-012', 'ManualTools', 'FM-10 Basic', 'Usinagem', 'Oficina Mecânica', '2015-05-01', '2024-01-15', 2.00, 'Peças/h', NULL),
(13, 'Furadeira de Bancada Antiga', 'Furadeira manual para furos de pequeno diâmetro.', 'operacional', 'SN-FURAD-ANTIGA', 'MAQ-013', 'VelhaGuarda', 'FB-500', 'Usinagem', 'Depósito', '2010-01-01', '2023-12-01', 10.00, 'Peças/h', NULL),
(14, 'Estampados', '1', 'operacional', '1', '1', '1', '1', '1', '1', '2025-06-06', '2025-06-06', 1.00, '1', '2025-06-06 19:23:48');

-- --------------------------------------------------------

--
-- Estrutura para tabela `materiais_insumos_entrada`
--

CREATE TABLE `materiais_insumos_entrada` (
  `id` int(11) NOT NULL,
  `data_entrada` datetime NOT NULL DEFAULT current_timestamp(),
  `produto_id` int(11) NOT NULL,
  `quantidade` decimal(10,2) NOT NULL,
  `valor_unitario` decimal(10,4) DEFAULT NULL,
  `numero_nota_fiscal` varchar(50) NOT NULL,
  `serie_nota_fiscal` varchar(10) DEFAULT NULL,
  `data_emissao_nota` date NOT NULL,
  `fornecedor_id` int(11) DEFAULT NULL,
  `local_armazenamento` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `responsavel_recebimento_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `materiais_insumos_entrada`
--

INSERT INTO `materiais_insumos_entrada` (`id`, `data_entrada`, `produto_id`, `quantidade`, `valor_unitario`, `numero_nota_fiscal`, `serie_nota_fiscal`, `data_emissao_nota`, `fornecedor_id`, `local_armazenamento`, `observacoes`, `responsavel_recebimento_id`, `deleted_at`) VALUES
(1, '2025-06-07 09:00:00', 1, 500.00, 1.2500, 'NF0012345', '1', '2025-06-05', 1, 'Almoxarifado Principal', 'Recebimento de lote de parafusos Philips.', 1, NULL),
(2, '2025-06-07 10:30:00', 2, 20.00, 18.7500, 'NF0012346', 'A', '2025-06-06', 2, 'Setor de Metais', 'Chapas de aço para corte a laser.', 2, NULL),
(3, '2025-06-07 11:45:00', 3, 10.00, 450.0000, 'NF0012347', 'UN', '2025-06-06', 3, 'Almoxarifado Principal', 'Resina plástica para injetora, lote 2025.', 1, NULL),
(4, '2025-06-07 13:15:00', 4, 150.00, 5.0000, 'NF0012348', '1', '2025-06-07', 1, 'Setor Eletrônico', 'Sensores de proximidade para montagem', 3, NULL),
(6, '2025-06-08 08:30:00', 5, 50.00, 15.0000, 'NF0012350', '2', '2025-06-07', 2, 'Setor de Corte', 'Rolamentos diversos.', 1, NULL),
(21, '2025-06-07 22:56:00', 5, 88.00, 150.0000, '123456', 'UN', '2025-06-07', 2, 'Almoxarifado Principal', '', 5, NULL),
(22, '2025-06-07 23:44:00', 43, 3000.00, 345.0000, '123452', 'UN', '2025-06-07', 4, 'Patio', '', 5, NULL),
(23, '2025-06-08 14:15:00', 47, 10000.00, 1.0000, '321564', '', '2025-06-08', 4, 'Almoxarifado Principal', '', 5, NULL),
(24, '2025-06-08 14:16:00', 47, 40000.00, 1.0000, '456789', '', '2025-06-08', 4, 'Almoxarifado Principal', '', 5, NULL),
(25, '2025-06-08 14:40:00', 43, 2500.00, 340.0000, '456123', '', '2025-06-08', 4, 'Patio', '', 5, NULL),
(26, '2025-06-08 16:10:00', 47, 200000.00, 1.0000, '12378', '', '2025-06-08', 6, 'Almoxarifado Principal', '', 5, NULL),
(27, '2025-06-14 18:00:00', 43, 100.00, 145.0000, '321564', 'A', '2025-06-14', 4, 'Patio', '', 5, NULL),
(28, '2025-06-14 23:58:00', 44, 2000.00, 145.0000, '456783', '', '2025-06-14', 6, 'Patio', '', 5, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelos_lookup`
--

CREATE TABLE `modelos_lookup` (
  `id` int(11) NOT NULL,
  `nome` varchar(20) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `modelos_lookup`
--

INSERT INTO `modelos_lookup` (`id`, `nome`, `descricao`) VALUES
(1, 'Standard', 'Modelo padrão.'),
(2, 'Compacto', 'Modelo de tamanho reduzido.'),
(3, 'Premium', 'Modelo de alta qualidade.'),
(4, 'Industrial', 'Modelo para uso industrial.'),
(5, 'Personalizado', 'Modelo sob medida.'),
(16, 'Taeda', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimentacoes_estoque`
--

CREATE TABLE `movimentacoes_estoque` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `tipo_movimentacao` enum('entrada','saida','ajuste','empenho','desempenho') NOT NULL,
  `quantidade` decimal(10,2) NOT NULL,
  `data_hora_movimentacao` datetime NOT NULL DEFAULT current_timestamp(),
  `origem_destino` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `movimentacoes_estoque`
--

INSERT INTO `movimentacoes_estoque` (`id`, `produto_id`, `tipo_movimentacao`, `quantidade`, `data_hora_movimentacao`, `origem_destino`, `observacoes`) VALUES
(3, 34, 'entrada', 260.00, '2025-06-06 20:34:58', 'Produção OP: 250001 (Máquina ID: 2)', 'a'),
(4, 34, 'entrada', 60.00, '2025-06-06 20:37:35', 'Produção OP: 250001 (Máquina ID: 2)', '2'),
(5, 33, 'entrada', 50.00, '2025-06-07 00:54:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 2) [EDITADO]', '50'),
(6, 33, 'entrada', 900.00, '2025-06-07 00:55:00', 'Produção OP: 250001 (Máquina ID: 11, Operador ID: 1) [EDITADO]', '910'),
(7, 33, 'entrada', 50.00, '2025-06-07 01:08:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 1)', '50'),
(8, 33, 'entrada', 99.00, '2025-06-06 22:25:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador: Jurandir Prestes (OP-001))', '90'),
(9, 33, 'entrada', 60.00, '2025-06-06 22:52:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 1) [EDITADO]', '50'),
(10, 33, 'entrada', 10.00, '2025-06-06 23:08:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 1)', '10'),
(11, 33, 'entrada', 10.00, '2025-06-06 23:10:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 1)', '10'),
(12, 33, 'entrada', 60.00, '2025-06-06 23:16:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 1)', '60'),
(13, 33, 'entrada', 555.00, '2025-06-06 23:30:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 1)', '555'),
(14, 33, 'saida', 555.00, '2025-06-07 02:50:58', 'Remoção Apontamento OP:  (Máquina ID: 11, Operador ID: 1) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 17'),
(15, 33, 'entrada', 99.00, '2025-06-06 23:50:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 1)', '9'),
(16, 33, 'saida', 99.00, '2025-06-07 02:51:13', 'Remoção Apontamento OP:  (Máquina ID: 11, Operador ID: 1) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 18'),
(17, 33, 'entrada', 9.00, '2025-06-06 23:52:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 1)', '99'),
(18, 33, 'saida', 9.00, '2025-06-07 02:52:14', 'Remoção Apontamento OP:  (Máquina ID: 11, Operador ID: 1) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 19'),
(19, 33, 'entrada', 99.00, '2025-06-06 23:54:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 1)', '999'),
(20, 33, 'saida', 99.00, '2025-06-07 02:54:44', 'Remoção Apontamento OP:  (Máquina ID: 11, Operador ID: 1) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 20'),
(21, 33, 'entrada', 999.00, '2025-06-07 00:00:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 1)', '999'),
(22, 33, 'saida', 999.00, '2025-06-07 03:00:17', 'Remoção Apontamento OP:  (Máquina ID: 5, Operador ID: 1) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 21'),
(23, 33, 'entrada', 9999.00, '2025-06-07 00:04:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 4)', '9999'),
(24, 33, 'saida', 9999.00, '2025-06-07 03:04:39', 'Remoção Apontamento OP: 2506757719 (Máquina ID: 11, Operador: Ana Costa (OP-004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 22'),
(25, 33, 'entrada', 600.00, '2025-06-07 00:38:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 1)', '600'),
(26, 30, 'entrada', 99.00, '2025-06-07 01:37:00', 'Produção OP: 2506521602 (Máquina ID: 5, Operador ID: 5)', ''),
(27, 30, 'entrada', 36.00, '2025-06-07 01:37:00', 'Produção OP: 2506607049 (Máquina ID: 5, Operador ID: 5)', ''),
(28, 30, 'entrada', 36.00, '2025-06-07 01:37:00', 'Produção OP: 2506607049 (Máquina ID: 5, Operador ID: 5)', ''),
(29, 30, 'saida', 36.00, '2025-06-07 04:38:14', 'Remoção Apontamento OP: 2506607049 (Máquina ID: 5, Operador: ) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 29'),
(30, 32, 'entrada', 50.00, '2025-06-07 01:38:00', 'Produção OP: 2506119190 (Máquina ID: 2, Operador ID: 4)', ''),
(31, 32, 'entrada', 50.00, '2025-06-07 01:38:00', 'Produção OP: 2506119190 (Máquina ID: 2, Operador ID: 4)', ''),
(32, 32, 'entrada', 50.00, '2025-06-07 01:38:00', 'Produção OP: 2506119190 (Máquina ID: 2, Operador ID: 4)', ''),
(33, 32, 'entrada', 50.00, '2025-06-07 01:38:00', 'Produção OP: 2506119190 (Máquina ID: 2, Operador ID: 4)', ''),
(34, 32, 'saida', 50.00, '2025-06-07 04:40:06', 'Remoção Apontamento OP: 2506119190 (Máquina ID: 2, Operador: Ana Costa (OP-004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 33'),
(35, 32, 'saida', 50.00, '2025-06-07 04:40:13', 'Remoção Apontamento OP: 2506119190 (Máquina ID: 2, Operador: Ana Costa (OP-004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 31'),
(36, 32, 'saida', 50.00, '2025-06-07 04:40:17', 'Remoção Apontamento OP: 2506119190 (Máquina ID: 2, Operador: Ana Costa (OP-004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 32'),
(37, 32, 'saida', 50.00, '2025-06-07 04:40:20', 'Remoção Apontamento OP: 2506119190 (Máquina ID: 2, Operador: Ana Costa (OP-004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 30'),
(38, 33, 'entrada', 100.00, '2025-06-07 01:41:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 5)', ''),
(39, 33, 'entrada', 100.00, '2025-06-07 01:42:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 5)', ''),
(40, 33, 'saida', 100.00, '2025-06-07 04:42:32', 'Remoção Apontamento OP: 2506354138 (Máquina ID: 5, Operador: ) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 35'),
(41, 33, 'saida', 100.00, '2025-06-07 04:42:35', 'Remoção Apontamento OP: 2506354138 (Máquina ID: 5, Operador: ) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 34'),
(42, 33, 'saida', 600.00, '2025-06-07 04:42:39', 'Remoção Apontamento OP: 2506354138 (Máquina ID: 5, Operador: Jurandir Prestes (OP-001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 23'),
(43, 33, 'entrada', 50.00, '2025-06-07 01:42:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 5)', ''),
(44, 33, 'saida', 60.00, '2025-06-07 04:42:55', 'Remoção Apontamento OP: 2506354138 (Máquina ID: 5, Operador: Jurandir Prestes (OP-001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 16'),
(45, 33, 'saida', 20.00, '2025-06-07 04:42:58', 'Remoção Apontamento OP: 2506354138 (Máquina ID: 5, Operador: Jurandir Prestes (OP-001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 14'),
(46, 33, 'saida', 60.00, '2025-06-07 04:52:00', 'Remoção Apontamento OP: 2506354138 (Máquina ID: 5, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 13'),
(47, 33, 'entrada', 50.00, '2025-06-07 01:52:00', 'Produção OP: 2506354138 (Máquina ID: 5, Operador ID: 5)', ''),
(48, 33, 'entrada', 99.00, '2025-06-07 18:09:00', 'Produção OP: 2506757719 (Máquina ID: 11, Operador ID: 5)', ''),
(49, 38, 'entrada', 99.00, '2025-06-07 18:11:00', 'Produção OP: 2506855997 (Máquina ID: 5, Operador ID: 5)', 'Teste'),
(50, 38, 'entrada', 99.00, '2025-06-07 18:15:00', 'Produção OP: 2506659847 (Máquina ID: 11, Operador ID: 5)', 'Teste'),
(51, 38, 'entrada', 50.00, '2025-06-07 18:23:00', 'Produção OP: 2506855997 (Máquina ID: 5, Operador ID: 5)', ''),
(52, 32, 'entrada', 120.00, '2025-06-07 18:39:00', 'Produção OP: 2506119190 (Máquina ID: 2, Operador ID: 5)', ''),
(53, 38, 'entrada', 100.00, '2025-06-07 20:57:00', 'Produção OP: 2506855997 (Máquina ID: 5, Operador ID: 5)', ''),
(54, 11, 'entrada', 201.00, '2025-06-07 21:11:00', 'NF: 1 (Fornecedor: Mega Distribuidores) [EDITADO]', ''),
(55, 36, 'entrada', 26.00, '2025-06-07 21:18:00', 'NF: 1 (Fornecedor: )', ''),
(56, 6, 'entrada', 201.00, '2025-06-07 21:22:00', 'NF: 1 (Fornecedor: ABC Componentes Ltda.) [EDITADO]', ''),
(57, 9, 'entrada', 160.00, '2025-06-07 21:22:00', 'NF: 1 (Fornecedor: Plastic Solutions ME) [EDITADO]', ''),
(58, 6, 'saida', 200.00, '2025-06-08 00:53:38', 'Remoção Entrada Material NF: NF0012351 (ID Entrada: 7)', 'Entrada de material/insumo excluída (soft delete).'),
(59, 5, 'entrada', 88.00, '2025-06-07 22:56:00', 'NF: 123456 (Fornecedor: Metalúrgica XYZ S.A.) [EDITADO]', ''),
(60, 5, 'entrada', 145.00, '2025-06-07 21:55:00', 'NF: 1 (Fornecedor: )', ''),
(61, 29, 'entrada', 1.00, '2025-06-07 22:00:00', 'NF: 1 (Fornecedor: )', ''),
(62, 29, 'entrada', 99.00, '2025-06-07 22:08:00', 'NF: 1 (Fornecedor: )', ''),
(63, 29, 'saida', 99.00, '2025-06-08 01:09:24', 'Remoção Entrada Material NF: 1 (ID Entrada: 15)', 'Entrada de material/insumo excluída (soft delete).'),
(64, 29, 'saida', 1.00, '2025-06-08 01:09:39', 'Remoção Entrada Material NF: 1 (ID Entrada: 14)', 'Entrada de material/insumo excluída (soft delete).'),
(65, 36, 'saida', 26.00, '2025-06-08 01:18:11', 'Remoção Entrada Material NF: 1 (ID Entrada: 9)', 'Entrada de material/insumo excluída (soft delete).'),
(66, 5, 'entrada', 1.00, '2025-06-07 22:19:00', 'NF: 1 (Fornecedor: 4)', ''),
(67, 33, 'entrada', 1.00, '2025-06-07 22:30:00', 'NF: 1 (Fornecedor: )', ''),
(68, 33, 'saida', 1.00, '2025-06-08 01:31:14', 'Remoção Entrada Material NF: 1 (ID Entrada: 17)', 'Entrada de material/insumo excluída (soft delete).'),
(69, 5, 'saida', 1.00, '2025-06-08 01:37:21', 'Remoção Entrada Material NF: 1 (ID Entrada: 16)', 'Entrada de material/insumo excluída (soft delete).'),
(70, 5, 'saida', 146.00, '2025-06-08 01:37:25', 'Remoção Entrada Material NF: 1 (ID Entrada: 13)', 'Entrada de material/insumo excluída (soft delete).'),
(71, 5, 'saida', 155.00, '2025-06-08 01:37:28', 'Remoção Entrada Material NF: 1 (ID Entrada: 12)', 'Entrada de material/insumo excluída (soft delete).'),
(72, 6, 'saida', 201.00, '2025-06-08 01:37:31', 'Remoção Entrada Material NF: 1 (ID Entrada: 10)', 'Entrada de material/insumo excluída (soft delete).'),
(73, 9, 'saida', 160.00, '2025-06-08 01:37:34', 'Remoção Entrada Material NF: 1 (ID Entrada: 11)', 'Entrada de material/insumo excluída (soft delete).'),
(74, 11, 'saida', 201.00, '2025-06-08 01:37:36', 'Remoção Entrada Material NF: 1 (ID Entrada: 8)', 'Entrada de material/insumo excluída (soft delete).'),
(75, 32, 'entrada', 3.00, '2025-06-07 22:38:00', 'NF: 1 (Fornecedor: )', ''),
(76, 32, 'saida', 3.00, '2025-06-08 01:40:45', 'Remoção Entrada Material NF: 1 (ID Entrada: 18)', 'Entrada de material/insumo excluída (soft delete).'),
(77, 5, 'entrada', 99.00, '2025-06-07 22:42:00', 'NF: 1 (Fornecedor: 1)', ''),
(78, 5, 'saida', 99.00, '2025-06-08 01:45:33', 'Remoção Entrada Material NF: 1 (ID Entrada: 19)', 'Entrada de material/insumo excluída (soft delete).'),
(79, 32, 'entrada', 1.00, '2025-06-07 22:47:00', 'NF: 1 (Fornecedor: 6)', ''),
(80, 32, 'saida', 1.00, '2025-06-08 01:54:00', 'Remoção Entrada Material NF: 1 (ID Entrada: 20)', 'Entrada de material/insumo excluída (soft delete).'),
(81, 5, 'entrada', 88.00, '2025-06-07 22:56:00', 'NF: 1 (Fornecedor: Metalúrgica XYZ S.A.)', ''),
(82, 43, 'entrada', 3000.00, '2025-06-07 23:44:00', 'NF: 123452 (Fornecedor: Global Logistics)', ''),
(83, 45, 'entrada', 100.00, '2025-06-08 01:09:00', 'Produção OP: 2506102927 (Máquina ID: 5, Operador ID: 4)', ''),
(84, 45, 'ajuste', 10.00, '2025-06-08 13:14:00', 'Refugado', 'Acerto'),
(85, 45, 'saida', 10.00, '2025-06-08 13:17:00', 'Refugado', 'lixo'),
(86, 45, 'entrada', 1900.00, '2025-06-08 13:23:00', 'Produção OP: 2506102927 (Máquina ID: 5, Operador ID: 5)', ''),
(87, 47, 'entrada', 10000.00, '2025-06-08 14:15:00', 'NF: 321564 (Fornecedor: Global Logistics) [EDITADO]', ''),
(88, 47, 'entrada', 40000.00, '2025-06-08 14:16:00', 'NF: 456789 (Fornecedor: Global Logistics)', ''),
(89, 45, 'entrada', 100.00, '2025-06-08 14:18:00', 'Produção OP: 2506096008 (Máquina ID: 5, Operador ID: 5)', ''),
(90, 45, 'saida', 100.00, '2025-06-08 17:21:44', 'Remoção Apontamento OP: 2506096008 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 46'),
(91, 45, 'entrada', 250.00, '2025-06-08 14:22:00', 'Produção OP: 2506096008 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(92, 14, 'saida', 2125.00, '2025-06-08 14:22:00', 'Consumo OP: 2506096008 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 47)'),
(93, 46, 'saida', 2250.00, '2025-06-08 14:22:00', 'Consumo OP: 2506096008 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 47)'),
(94, 47, 'saida', 20000.00, '2025-06-08 14:22:00', 'Consumo OP: 2506096008 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 47)'),
(95, 14, 'entrada', 2200.00, '2025-06-08 14:35:00', 'Produção OP: 2506353264 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(96, 4, 'saida', 2926.00, '2025-06-08 14:35:00', 'Consumo OP: 2506353264 (Prod. Acabado: R56226383889)', 'Consumo de material para produção (Apontamento ID: 48)'),
(97, 43, 'entrada', 2500.00, '2025-06-08 14:40:00', 'NF: 456123 (Fornecedor: Global Logistics)', ''),
(98, 4, 'entrada', 1400.00, '2025-06-08 14:41:00', 'Produção OP: 2506595644 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(99, 43, 'saida', 2520.00, '2025-06-08 14:41:00', 'Consumo OP: 2506595644 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 49)'),
(100, 4, 'entrada', 1400.00, '2025-06-08 14:45:00', 'Produção OP: 2506595644 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(101, 43, 'saida', 2520.00, '2025-06-08 14:45:00', 'Consumo OP: 2506595644 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 50)'),
(102, 14, 'entrada', 2100.00, '2025-06-08 14:49:00', 'Produção OP: 2506868668 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(103, 4, 'saida', 2793.00, '2025-06-08 14:49:00', 'Consumo OP: 2506868668 (Prod. Acabado: R56226383889)', 'Consumo de material para produção (Apontamento ID: 51)'),
(104, 4, 'entrada', 2919.00, '2025-06-08 14:50:00', '', 'acerto'),
(105, 45, 'entrada', 500.00, '2025-06-08 14:53:00', 'Produção OP: 2506096008 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(106, 14, 'saida', 4250.00, '2025-06-08 14:53:00', 'Consumo OP: 2506096008 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 52)'),
(107, 46, 'saida', 4500.00, '2025-06-08 14:53:00', 'Consumo OP: 2506096008 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 52)'),
(108, 47, 'saida', 40000.00, '2025-06-08 14:53:00', 'Consumo OP: 2506096008 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 52)'),
(109, 33, 'entrada', 1.00, '2025-06-08 14:56:00', 'Produção OP: 2506356118 (Máquina ID: 2, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(110, 33, 'entrada', 1.00, '2025-06-08 15:05:00', 'Produção OP: 2506356118 (Máquina ID: 2, Operador ID: 1)', 'Entrada de produto acabado por apontamento.'),
(111, 33, 'entrada', 1.00, '2025-06-08 15:05:00', 'Produção OP: 2506356118 (Máquina ID: 2, Operador ID: 3)', 'Entrada de produto acabado por apontamento.'),
(112, 33, 'saida', 1.00, '2025-06-08 18:05:58', 'Remoção Apontamento OP: 2506356118 (Máquina ID: 2, Operador: Rafaela Costa (M003)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 55'),
(113, 33, 'saida', 1.00, '2025-06-08 18:06:20', 'Remoção Apontamento OP: 2506356118 (Máquina ID: 2, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 54'),
(114, 33, 'saida', 1.00, '2025-06-08 18:06:22', 'Remoção Apontamento OP: 2506356118 (Máquina ID: 2, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 53'),
(115, 33, 'entrada', 99.00, '2025-06-08 15:06:00', 'Produção OP: 2506356118 (Máquina ID: 2, Operador ID: 4)', 'Entrada de produto acabado por apontamento.'),
(116, 33, 'saida', 99.00, '2025-06-08 18:07:43', 'Remoção Apontamento OP: 2506356118 (Máquina ID: 2, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 56'),
(117, 30, 'saida', 99.00, '2025-06-08 18:08:59', 'Remoção Apontamento OP: 2506521602 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 27'),
(118, 14, 'saida', 2200.00, '2025-06-08 18:10:48', 'Remoção Apontamento OP: 2506353264 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 48'),
(119, 45, 'saida', 250.00, '2025-06-08 18:11:45', 'Remoção Apontamento OP: 2506096008 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 47'),
(120, 45, 'saida', 500.00, '2025-06-08 18:11:47', 'Remoção Apontamento OP: 2506096008 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 52'),
(121, 14, 'saida', 2100.00, '2025-06-08 18:12:22', 'Remoção Apontamento OP: 2506868668 (Máquina ID: 11, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 51'),
(122, 47, 'entrada', 10000.00, '2025-06-08 15:14:00', 'Acerto', 'acert'),
(123, 14, 'entrada', 6375.00, '2025-06-08 15:15:00', 'Acerto', ''),
(124, 46, 'entrada', 6750.00, '2025-06-08 15:15:00', 'Acerto', ''),
(125, 47, 'entrada', 200000.00, '2025-06-08 16:10:00', 'Acerto', ''),
(126, 47, 'entrada', 200000.00, '2025-06-08 16:10:00', 'NF: 12378 (Fornecedor: Mega Distribuidores) [EDITADO]', ''),
(127, 14, 'entrada', 500.00, '2025-06-08 16:14:00', 'Acerto', ''),
(128, 14, 'entrada', 42000.00, '2025-06-08 16:16:00', 'Acerto', ''),
(129, 45, 'entrada', 2500.00, '2025-06-09 16:18:00', 'Produção OP: 2506218500 (Máquina ID: 5, Operador ID: 5) [EDITADO]', ''),
(130, 14, 'saida', 21250.00, '2025-06-08 16:18:00', 'Consumo OP: 2506218500 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 57)'),
(131, 46, 'saida', 22500.00, '2025-06-08 16:18:00', 'Consumo OP: 2506218500 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 57)'),
(132, 47, 'saida', 200000.00, '2025-06-08 16:18:00', 'Consumo OP: 2506218500 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 57)'),
(133, 46, 'entrada', 40000.00, '2025-06-08 16:21:00', 'Acerto', ''),
(134, 46, 'entrada', 5000.00, '2025-06-08 16:22:00', 'Produção OP: 2506862308 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(135, 46, 'saida', 5000.00, '2025-06-08 19:23:06', 'Remoção Apontamento OP: 2506862308 (Máquina ID: 11, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 58'),
(136, 46, 'entrada', 5000.00, '2025-06-08 16:23:00', 'Produção OP: 2506862308 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(137, 45, 'entrada', 2500.00, '2025-06-08 16:23:00', 'Produção OP: 2506218500 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(138, 14, 'saida', 21250.00, '2025-06-08 16:23:00', 'Consumo OP: 2506218500 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 60)'),
(139, 46, 'saida', 22500.00, '2025-06-08 16:23:00', 'Consumo OP: 2506218500 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 60)'),
(140, 47, 'saida', 200000.00, '2025-06-08 16:23:00', 'Consumo OP: 2506218500 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 60)'),
(141, 36, 'empenho', 70.00, '2025-06-08 19:23:00', 'Empenho Manual OP:  (Resp: Jurandir Prestes (M001))', ''),
(142, 36, 'desempenho', 70.00, '2025-06-08 22:28:53', 'Desempenho Manual OP: 2506285156 (Empenho ID: 19)', 'Empenho manual excluído (ID: 19) - Quantidade liberada do empenho.'),
(143, 4, 'entrada', 30.00, '2025-06-09 19:42:00', 'Produção OP: 2506037382 (Máquina ID: 11, Operador ID: 5) [EDITADO]', ''),
(144, 43, 'saida', 54.00, '2025-06-08 19:42:00', 'Consumo OP: 2506037382 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 61)'),
(145, 45, 'entrada', 1000.00, '2025-06-08 19:44:00', 'Produção OP: 2506967993 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento.'),
(146, 14, 'saida', 8500.00, '2025-06-08 19:44:00', 'Consumo OP: 2506967993 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 62)'),
(147, 46, 'saida', 9000.00, '2025-06-08 19:44:00', 'Consumo OP: 2506967993 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 62)'),
(148, 47, 'saida', 80000.00, '2025-06-08 19:44:00', 'Consumo OP: 2506967993 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 62)'),
(149, 45, 'saida', 1000.00, '2025-06-08 19:43:00', 'Lote: 2506967993-62 (Destino: Mega Distribuidores)', ' Lote: 2506967993-62'),
(150, 4, 'entrada', 31.00, '2025-06-08 20:27:00', 'Produção OP: 2506037382 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506037382-63'),
(151, 43, 'saida', 55.80, '2025-06-08 20:27:00', 'Consumo OP: 2506037382 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 63)'),
(152, 46, 'entrada', 9000.00, '2025-06-08 20:50:00', 'Acerto', ''),
(153, 46, 'entrada', 30996.00, '2025-06-08 20:51:00', '', ''),
(154, 4, 'entrada', 55.00, '2025-06-08 21:53:00', 'Produção OP: 2506037382 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506037382-64'),
(155, 43, 'saida', 99.00, '2025-06-08 21:53:00', 'Consumo OP: 2506037382 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 64)'),
(156, 4, 'saida', 55.00, '2025-06-09 00:53:57', 'Remoção Apontamento OP: 2506037382 (Máquina ID: 11, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 64'),
(157, 45, 'saida', 1900.00, '2025-06-09 01:22:08', 'Remoção Apontamento OP: 2506102927 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 45'),
(158, 36, 'empenho', 1.00, '2025-06-08 22:25:00', 'Empenho Manual OP:  (Resp: Jurandir Prestes (M001))', ''),
(159, 45, 'entrada', 1500.00, '2025-06-08 22:52:00', 'Produção OP: 2506517118 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506517118-65'),
(160, 14, 'saida', 12750.00, '2025-06-08 22:52:00', 'Consumo OP: 2506517118 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 65)'),
(161, 46, 'saida', 13500.00, '2025-06-08 22:52:00', 'Consumo OP: 2506517118 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 65)'),
(162, 47, 'saida', 120000.00, '2025-06-08 22:52:00', 'Consumo OP: 2506517118 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 65)'),
(163, 45, 'saida', 1500.00, '2025-06-09 02:12:28', 'Remoção Apontamento OP: 2506517118 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 65'),
(164, 4, 'entrada', 200.00, '2025-06-08 23:33:00', 'Produção OP: 2506037382 (Máquina ID: 11, Operador ID: 2)', 'Entrada de produto acabado por apontamento. Lote: 2506037382-66'),
(165, 43, 'saida', 360.00, '2025-06-08 23:33:00', 'Consumo OP: 2506037382 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 66)'),
(166, 45, 'entrada', 1.00, '2025-06-09 00:05:00', 'Produção OP: 2506704381 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506704381-67'),
(167, 14, 'saida', 8.50, '2025-06-09 00:05:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 67)'),
(168, 46, 'saida', 9.00, '2025-06-09 00:05:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 67)'),
(169, 47, 'saida', 80.00, '2025-06-09 00:05:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 67)'),
(170, 45, 'saida', 1.00, '2025-06-09 03:06:24', 'Remoção Apontamento OP: 2506704381 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 67'),
(171, 45, 'entrada', 1400.00, '2025-06-09 00:28:00', 'Produção OP: 2506704381 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506704381-68'),
(172, 14, 'saida', 11900.00, '2025-06-09 00:28:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 68)'),
(173, 46, 'saida', 12600.00, '2025-06-09 00:28:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 68)'),
(174, 47, 'saida', 112000.00, '2025-06-09 00:28:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 68)'),
(175, 45, 'saida', 1000.00, '2025-06-09 03:36:46', 'Remoção Apontamento OP: 2506967993 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 62'),
(176, 4, 'entrada', 19.50, '2025-06-09 00:47:00', 'Produção OP: 2506037382 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506037382-69'),
(177, 43, 'saida', 35.10, '2025-06-09 00:47:00', 'Consumo OP: 2506037382 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 69)'),
(178, 4, 'saida', 19.50, '2025-06-09 03:50:37', 'Remoção Apontamento OP: 2506037382 (Máquina ID: 11, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 69'),
(179, 4, 'saida', 31.00, '2025-06-09 03:50:56', 'Remoção Apontamento OP: 2506037382 (Máquina ID: 11, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 63'),
(180, 4, 'saida', 30.00, '2025-06-09 03:51:00', 'Remoção Apontamento OP: 2506037382 (Máquina ID: 11, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 61'),
(181, 43, 'desempenho', 180.00, '2025-06-08 23:35:00', 'Desempenho Manual OP: 2506037382 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506037382'),
(182, 4, 'entrada', 50.00, '2025-06-09 00:56:00', 'Produção OP: 2506037382 (Máquina ID: 11, Operador ID: 1)', 'Entrada de produto acabado por apontamento. Lote: 2506037382-70'),
(183, 43, 'saida', 90.00, '2025-06-09 00:56:00', 'Consumo OP: 2506037382 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 70)'),
(184, 4, 'saida', 50.00, '2025-06-09 03:57:24', 'Remoção Apontamento OP: 2506037382 (Máquina ID: 11, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 70'),
(185, 4, 'entrada', 100.00, '2025-06-09 01:07:00', 'Produção OP: 2506037382 (Máquina ID: 11, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506037382-71'),
(186, 43, 'saida', 180.00, '2025-06-09 01:07:00', 'Consumo OP: 2506037382 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 71)'),
(187, 45, 'entrada', 10.00, '2025-06-09 10:56:00', 'Produção OP: 2506704381 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506704381-72'),
(188, 14, 'saida', 85.00, '2025-06-09 10:56:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 72)'),
(189, 46, 'saida', 90.00, '2025-06-09 10:56:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 72)'),
(190, 47, 'saida', 800.00, '2025-06-09 10:56:00', 'Consumo OP: 2506704381 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 72)'),
(191, 45, 'entrada', 333.00, '2025-06-09 13:47:00', 'Produção OP: 2506363416 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506363416-73'),
(192, 14, 'saida', 2830.50, '2025-06-09 13:47:00', 'Consumo OP: 2506363416 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 73)'),
(193, 46, 'saida', 2997.00, '2025-06-09 13:47:00', 'Consumo OP: 2506363416 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 73)'),
(194, 47, 'saida', 26640.00, '2025-06-09 13:47:00', 'Consumo OP: 2506363416 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 73)'),
(195, 45, 'entrada', 222.00, '2025-06-09 14:18:00', 'Produção OP: 2506363416 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506363416-74'),
(196, 14, 'saida', 1887.00, '2025-06-09 14:18:00', 'Consumo OP: 2506363416 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 74)'),
(197, 46, 'saida', 1998.00, '2025-06-09 14:18:00', 'Consumo OP: 2506363416 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 74)'),
(198, 47, 'saida', 17760.00, '2025-06-09 14:18:00', 'Consumo OP: 2506363416 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 74)'),
(199, 43, 'desempenho', 180.00, '2025-06-09 17:19:10', 'Desempenho Manual OP: 2506037382 (Empenho ID: 20)', 'Empenho manual excluído (ID: 20) - Quantidade liberada do empenho.'),
(200, 47, 'desempenho', 26640.00, '2025-06-09 17:19:14', 'Desempenho Manual OP: 2506363416 (Empenho ID: 39)', 'Empenho manual excluído (ID: 39) - Quantidade liberada do empenho.'),
(201, 46, 'desempenho', 2997.00, '2025-06-09 17:19:15', 'Desempenho Manual OP: 2506363416 (Empenho ID: 38)', 'Empenho manual excluído (ID: 38) - Quantidade liberada do empenho.'),
(202, 14, 'desempenho', 2830.50, '2025-06-09 17:19:17', 'Desempenho Manual OP: 2506363416 (Empenho ID: 37)', 'Empenho manual excluído (ID: 37) - Quantidade liberada do empenho.'),
(203, 45, 'saida', 1400.00, '2025-06-09 17:21:17', 'Remoção Apontamento OP: 2506704381 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 68'),
(204, 45, 'saida', 10.00, '2025-06-09 17:21:20', 'Remoção Apontamento OP: 2506704381 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 72'),
(205, 45, 'entrada', 750.00, '2025-06-09 14:25:00', 'Produção OP: 2506156257 (Máquina ID: 5, Operador ID: 5) [EDITADO]', ''),
(206, 14, 'saida', 8500.00, '2025-06-09 14:25:00', 'Consumo OP: 2506156257 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 75)'),
(207, 46, 'saida', 9000.00, '2025-06-09 14:25:00', 'Consumo OP: 2506156257 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 75)'),
(208, 47, 'saida', 80000.00, '2025-06-09 14:25:00', 'Consumo OP: 2506156257 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 75)'),
(209, 47, 'desempenho', 80000.00, '2025-06-09 14:25:00', 'Desempenho Manual OP: 2506156257 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506156257'),
(210, 46, 'desempenho', 9000.00, '2025-06-09 14:25:00', 'Desempenho Manual OP: 2506156257 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506156257'),
(211, 14, 'desempenho', 8500.00, '2025-06-09 14:25:00', 'Desempenho Manual OP: 2506156257 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506156257'),
(212, 14, 'desempenho', 6375.00, '2025-06-09 14:30:00', 'Desempenho Manual OP: 2506156257 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506156257'),
(213, 46, 'desempenho', 6750.00, '2025-06-09 14:30:00', 'Desempenho Manual OP: 2506156257 (Resp: Fernando Alves (M004))', 'Empenho automático para OP 2506156257'),
(214, 47, 'desempenho', 60000.00, '2025-06-09 14:30:00', 'Desempenho Manual OP: 2506156257 (Resp: Carlos Silva (M005))', 'Empenho automático para OP 2506156257'),
(215, 47, 'desempenho', 80000.00, '2025-06-09 15:24:00', 'Desempenho Manual OP: 2506156257 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506156257'),
(216, 45, 'saida', 750.00, '2025-06-09 18:52:20', 'Remoção Apontamento OP: 2506156257 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 75'),
(217, 45, 'entrada', 1000.00, '2025-06-09 15:52:00', 'Produção OP: 2506156257 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506156257-76'),
(218, 14, 'saida', 8500.00, '2025-06-09 15:52:00', 'Consumo OP: 2506156257 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 76)'),
(219, 46, 'saida', 9000.00, '2025-06-09 15:52:00', 'Consumo OP: 2506156257 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 76)'),
(220, 47, 'saida', 80000.00, '2025-06-09 15:52:00', 'Consumo OP: 2506156257 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 76)'),
(221, 36, 'empenho', 50.00, '2025-06-09 16:30:00', 'Empenho Manual OP:  (Resp: Jurandir Prestes (M001))', ''),
(222, 36, 'empenho', 75.00, '2025-06-09 16:30:00', 'Empenho Manual OP: 2506156257 (Resp: Fernando Alves (M004))', ''),
(223, 36, 'desempenho', 75.00, '2025-06-09 19:55:15', 'Desempenho Manual OP: 2506156257 (Empenho ID: 49)', 'Empenho manual excluído (ID: 49) - Quantidade liberada do empenho.'),
(224, 36, 'empenho', 75.00, '2025-06-09 16:55:00', 'Empenho Manual OP:  (Resp: Jurandir Prestes (M001))', ''),
(225, 36, 'empenho', 50.00, '2025-06-09 17:15:00', 'Empenho Manual OP:  (Resp: Jurandir Prestes (M001))', 'OP 2506156257'),
(226, 36, 'desempenho', 50.00, '2025-06-09 20:16:18', 'Desempenho Manual OP: 2506156257 (Empenho ID: 51)', 'Empenho manual excluído (ID: 51) - Quantidade liberada do empenho.'),
(227, 45, 'saida', 1000.00, '2025-06-09 20:25:07', 'Remoção Apontamento OP: 2506156257 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 76'),
(228, 45, 'entrada', 500.00, '2025-06-09 17:31:00', 'Produção OP: 2506189834 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506189834-77'),
(229, 14, 'saida', 4250.00, '2025-06-09 17:31:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 77)'),
(230, 46, 'saida', 4500.00, '2025-06-09 17:31:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 77)'),
(231, 47, 'saida', 40000.00, '2025-06-09 17:31:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 77)'),
(232, 45, 'entrada', 500.00, '2025-06-09 17:42:00', 'Produção OP: 2506189834 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506189834-78'),
(233, 14, 'saida', 4250.00, '2025-06-09 17:42:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 78)'),
(234, 46, 'saida', 4500.00, '2025-06-09 17:42:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 78)'),
(235, 47, 'saida', 40000.00, '2025-06-09 17:42:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 78)'),
(236, 45, 'entrada', 250.00, '2025-06-09 17:44:00', 'Produção OP: 2506189834 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506189834-79'),
(237, 14, 'saida', 2125.00, '2025-06-09 17:44:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 79)'),
(238, 46, 'saida', 2250.00, '2025-06-09 17:44:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 79)'),
(239, 47, 'saida', 20000.00, '2025-06-09 17:44:00', 'Consumo OP: 2506189834 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 79)'),
(240, 14, 'empenho', 1000.00, '2025-06-09 18:49:00', 'Empenho Manual OP: 2506189834 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506189834'),
(241, 47, 'empenho', 1000.00, '2025-06-09 18:49:00', 'Empenho Manual OP: 2506189834 (Resp: Rosekelly Cirineu (M006))', 'Empenho automático para OP 2506189834'),
(242, 46, 'empenho', 1000.00, '2025-06-09 18:49:00', 'Empenho Manual OP: 2506189834 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506189834'),
(243, 47, 'desempenho', 5000.00, '2025-06-09 22:19:02', 'Desempenho Manual OP: 2506189834 (Empenho ID: 54)', 'Empenho manual excluído (ID: 54) - Quantidade liberada do empenho.'),
(244, 45, 'entrada', 500.00, '2025-06-09 20:34:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001))', 'Entrada de produto acabado por apontamento. Lote: 2506401132-80'),
(245, 14, 'saida', 4250.00, '2025-06-09 20:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 80)'),
(246, 46, 'saida', 4500.00, '2025-06-09 20:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 80)'),
(247, 47, 'saida', 40000.00, '2025-06-09 20:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 80)'),
(248, 45, 'entrada', 250.00, '2025-06-09 20:41:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001))', 'Entrada de produto acabado por apontamento. Lote: 2506401132-81'),
(249, 14, 'saida', 2125.00, '2025-06-09 20:41:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 81)'),
(250, 46, 'saida', 2250.00, '2025-06-09 20:41:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 81)'),
(251, 47, 'saida', 20000.00, '2025-06-09 20:41:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 81)'),
(252, 44, 'entrada', 1000.00, '2025-06-09 21:05:00', 'Acerto', ''),
(253, 44, 'empenho', 1000.00, '2025-06-09 21:04:00', 'Empenho Manual OP:  (Resp: Jurandir Prestes (M001))', ''),
(254, 45, 'entrada', 250.00, '2025-06-09 21:08:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001))', 'Entrada de produto acabado por apontamento. Lote: 2506401132-82'),
(255, 45, 'entrada', 500.00, '2025-06-09 21:50:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001))', 'Entrada de produto acabado por apontamento. Lote: 2506401132-83'),
(256, 45, 'entrada', 1.00, '2025-06-09 22:23:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001))', 'Entrada de produto acabado por apontamento. Lote: 2506401132-84'),
(257, 45, 'saida', 1.00, '2025-06-10 01:25:51', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 84'),
(258, 45, 'saida', 250.00, '2025-06-10 01:26:03', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 82'),
(259, 46, 'entrada', 8000.00, '2025-06-09 22:26:00', 'Produção OP: 2506555475 (Máquina ID: 13, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506555475-85'),
(260, 45, 'entrada', 1.00, '2025-06-09 22:59:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador: Rafael (M003))', 'Entrada de produto acabado por apontamento. Lote: 2506401132-86'),
(261, 45, 'entrada', 1.00, '2025-06-09 22:58:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001))', 'Entrada de produto acabado por apontamento. Lote: 2506401132-87'),
(262, 45, 'saida', 1.00, '2025-06-10 02:04:50', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 87'),
(263, 45, 'saida', 1.00, '2025-06-10 02:05:05', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Rafael (M003)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 86'),
(264, 45, 'entrada', 50.00, '2025-06-09 23:05:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador: Carlos Silva (M005))', 'Entrada de produto acabado por apontamento. Lote: 2506401132-88'),
(265, 45, 'saida', 50.00, '2025-06-10 02:06:19', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 88'),
(266, 45, 'entrada', 1.00, '2025-06-09 23:06:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-89'),
(267, 14, 'saida', 8.50, '2025-06-09 23:06:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 89)'),
(268, 46, 'saida', 9.00, '2025-06-09 23:06:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 89)'),
(269, 47, 'saida', 80.00, '2025-06-09 23:06:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 89)'),
(270, 45, 'saida', 1.00, '2025-06-10 02:06:47', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 89'),
(271, 44, 'desempenho', 1000.00, '2025-06-10 02:07:34', 'Desempenho Manual OP: 2506555475 (Empenho ID: 62)', 'Empenho manual excluído (ID: 62) - Quantidade liberada do empenho.'),
(272, 46, 'saida', 8000.00, '2025-06-10 02:07:48', 'Remoção Apontamento OP: 2506555475 (Máquina ID: 13, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 85'),
(273, 45, 'entrada', 1.00, '2025-06-10 00:07:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-90'),
(274, 14, 'saida', 8.50, '2025-06-10 00:07:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 90)'),
(275, 46, 'saida', 9.00, '2025-06-10 00:07:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 90)'),
(276, 47, 'saida', 80.00, '2025-06-10 00:07:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 90)'),
(277, 45, 'entrada', 1.00, '2025-06-10 00:08:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 1)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-91'),
(278, 14, 'saida', 8.50, '2025-06-10 00:08:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 91)'),
(279, 46, 'saida', 9.00, '2025-06-10 00:08:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 91)'),
(280, 47, 'saida', 80.00, '2025-06-10 00:08:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 91)'),
(281, 45, 'entrada', 1.00, '2025-06-10 00:11:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-92'),
(282, 14, 'saida', 8.50, '2025-06-10 00:11:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 92)'),
(283, 46, 'saida', 9.00, '2025-06-10 00:11:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 92)'),
(284, 47, 'saida', 80.00, '2025-06-10 00:11:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 92)'),
(285, 45, 'saida', 1.00, '2025-06-10 03:14:15', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 92'),
(286, 45, 'saida', 1.00, '2025-06-10 03:14:17', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 91'),
(287, 45, 'saida', 1.00, '2025-06-10 03:14:20', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 90'),
(288, 45, 'entrada', 1.00, '2025-06-10 00:39:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-93'),
(289, 14, 'saida', 8.50, '2025-06-10 00:39:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 93)'),
(290, 46, 'saida', 9.00, '2025-06-10 00:39:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 93)'),
(291, 47, 'saida', 80.00, '2025-06-10 00:39:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 93)'),
(292, 45, 'saida', 1.00, '2025-06-10 03:40:00', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 93'),
(293, 45, 'entrada', 50.00, '2025-06-12 14:37:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-94'),
(294, 14, 'saida', 425.00, '2025-06-12 14:37:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 94)'),
(295, 46, 'saida', 450.00, '2025-06-12 14:37:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 94)'),
(296, 47, 'saida', 4000.00, '2025-06-12 14:37:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 94)'),
(297, 36, 'empenho', 11.00, '2025-06-13 11:39:00', 'Empenho Manual OP:  (Resp: Jurandir Prestes (M001))', ''),
(298, 36, 'empenho', 15.00, '2025-06-13 11:39:00', 'Empenho Manual OP: 2506401132 (Resp: Jurandir Prestes (M001))', ''),
(299, 45, 'entrada', 11.00, '2025-06-13 11:56:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-95'),
(300, 14, 'saida', 93.50, '2025-06-13 11:56:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 95)'),
(301, 46, 'saida', 99.00, '2025-06-13 11:56:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 95)'),
(302, 47, 'saida', 880.00, '2025-06-13 11:56:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 95)'),
(303, 45, 'entrada', 12.00, '2025-06-13 11:57:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-96'),
(304, 14, 'saida', 102.00, '2025-06-13 11:57:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 96)'),
(305, 46, 'saida', 108.00, '2025-06-13 11:57:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 96)'),
(306, 47, 'saida', 960.00, '2025-06-13 11:57:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 96)'),
(307, 45, 'saida', 11.00, '2025-06-13 14:59:26', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 95'),
(308, 45, 'saida', 12.00, '2025-06-13 14:59:29', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 96'),
(309, 45, 'entrada', 11.00, '2025-06-13 12:00:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-97'),
(310, 14, 'saida', 93.50, '2025-06-13 12:00:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 97)'),
(311, 46, 'saida', 99.00, '2025-06-13 12:00:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 97)'),
(312, 47, 'saida', 880.00, '2025-06-13 12:00:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 97)'),
(313, 45, 'entrada', 12.00, '2025-06-13 12:03:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-98'),
(314, 14, 'saida', 102.00, '2025-06-13 12:03:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 98)'),
(315, 46, 'saida', 108.00, '2025-06-13 12:03:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 98)'),
(316, 47, 'saida', 960.00, '2025-06-13 12:03:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 98)'),
(317, 45, 'saida', 12.00, '2025-06-13 15:05:02', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 98'),
(318, 45, 'saida', 11.00, '2025-06-13 15:05:05', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 97'),
(319, 45, 'entrada', 11.00, '2025-06-13 12:05:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-99'),
(320, 14, 'saida', 93.50, '2025-06-13 12:05:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 99)'),
(321, 46, 'saida', 99.00, '2025-06-13 12:05:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 99)'),
(322, 47, 'saida', 880.00, '2025-06-13 12:05:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 99)'),
(323, 45, 'entrada', 1.00, '2025-06-13 12:11:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-100'),
(324, 14, 'saida', 8.50, '2025-06-13 12:11:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 100)'),
(325, 46, 'saida', 9.00, '2025-06-13 12:11:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 100)'),
(326, 47, 'saida', 80.00, '2025-06-13 12:11:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 100)');
INSERT INTO `movimentacoes_estoque` (`id`, `produto_id`, `tipo_movimentacao`, `quantidade`, `data_hora_movimentacao`, `origem_destino`, `observacoes`) VALUES
(327, 45, 'saida', 1.00, '2025-06-13 15:23:19', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 100'),
(328, 45, 'saida', 11.00, '2025-06-13 15:23:37', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 99'),
(329, 45, 'entrada', 13.00, '2025-06-13 12:26:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-101'),
(330, 14, 'saida', 110.50, '2025-06-13 12:26:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 101)'),
(331, 46, 'saida', 117.00, '2025-06-13 12:26:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 101)'),
(332, 47, 'saida', 1040.00, '2025-06-13 12:26:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 101)'),
(333, 45, 'entrada', 17.00, '2025-06-13 12:33:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-102'),
(334, 14, 'saida', 144.50, '2025-06-13 12:33:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 102)'),
(335, 46, 'saida', 153.00, '2025-06-13 12:33:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 102)'),
(336, 47, 'saida', 1360.00, '2025-06-13 12:33:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 102)'),
(337, 45, 'entrada', 18.00, '2025-06-13 12:34:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-103'),
(338, 14, 'saida', 153.00, '2025-06-13 12:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 103)'),
(339, 46, 'saida', 162.00, '2025-06-13 12:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 103)'),
(340, 47, 'saida', 1440.00, '2025-06-13 12:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 103)'),
(341, 45, 'entrada', 99.00, '2025-06-13 12:37:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-104'),
(342, 14, 'saida', 841.50, '2025-06-13 12:37:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 104)'),
(343, 46, 'saida', 891.00, '2025-06-13 12:37:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 104)'),
(344, 47, 'saida', 7920.00, '2025-06-13 12:37:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 104)'),
(345, 45, 'saida', 99.00, '2025-06-13 15:37:52', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 104'),
(346, 45, 'saida', 18.00, '2025-06-13 15:37:55', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 103'),
(347, 45, 'saida', 17.00, '2025-06-13 15:37:58', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 102'),
(348, 45, 'saida', 13.00, '2025-06-13 15:38:01', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 101'),
(349, 45, 'saida', 500.00, '2025-06-13 13:04:08', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 80'),
(350, 45, 'saida', 50.00, '2025-06-13 13:21:26', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 94'),
(351, 45, 'saida', 50.00, '2025-06-13 13:22:21', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 94'),
(352, 45, 'saida', 50.00, '2025-06-13 13:27:09', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 94'),
(353, 45, 'saida', 500.00, '2025-06-13 13:31:23', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 83'),
(354, 45, 'saida', 250.00, '2025-06-13 13:33:51', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 81'),
(355, 45, 'saida', 500.00, '2025-06-13 13:37:47', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 80'),
(356, 45, 'saida', 250.00, '2025-06-13 13:38:25', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 81'),
(357, 45, 'entrada', 250.00, '2025-06-13 13:44:08', 'Estorno Consumo', 'Estorno do Consumo ID: 14'),
(358, 45, 'entrada', 500.00, '2025-06-13 13:46:11', 'Estorno Consumo', 'Estorno do Consumo ID: 13'),
(359, 45, 'entrada', 9.00, '2025-06-13 13:52:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 4)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-105'),
(360, 14, 'saida', 76.50, '2025-06-13 13:52:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 105)'),
(361, 46, 'saida', 81.00, '2025-06-13 13:52:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 105)'),
(362, 47, 'saida', 720.00, '2025-06-13 13:52:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 105)'),
(363, 45, 'saida', 9.00, '2025-06-13 13:53:42', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 105'),
(364, 45, 'saida', 1.00, '2025-06-13 16:54:46', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 87'),
(365, 45, 'entrada', 9.00, '2025-06-13 14:16:02', 'Estorno Consumo', 'Estorno do Consumo ID: 15'),
(366, 45, 'saida', 500.00, '2025-06-13 16:37:40', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 83'),
(367, 45, 'entrada', 500.00, '2025-06-13 16:38:07', 'Estorno Consumo', 'Estorno do Consumo ID: 16'),
(368, 36, 'desempenho', 15.00, '2025-06-13 20:04:50', 'Desempenho Manual OP: 2506401132 (Empenho ID: 64)', 'Empenho manual excluído (ID: 64) - Quantidade liberada do empenho.'),
(369, 47, 'saida', 1000.00, '2025-06-13 17:07:24', 'Produção', 'Consumo de Insumo para OP'),
(370, 47, 'saida', 10000.00, '2025-06-13 17:11:10', 'Produção', 'Consumo de Insumo para OP'),
(371, 47, 'saida', 18480.00, '2025-06-13 17:11:43', 'Produção', 'Consumo de Insumo para OP'),
(372, 47, 'saida', 18480.00, '2025-06-13 17:12:32', 'Produção', 'Consumo de Insumo para OP'),
(373, 47, 'entrada', 1000.00, '2025-06-13 17:12:54', 'Estorno Insumo', 'Estorno do Consumo de Insumo ID: 17'),
(374, 47, 'entrada', 18480.00, '2025-06-13 17:12:57', 'Estorno Insumo', 'Estorno do Consumo de Insumo ID: 20'),
(375, 47, 'entrada', 18480.00, '2025-06-13 17:13:00', 'Estorno Insumo', 'Estorno do Consumo de Insumo ID: 19'),
(376, 45, 'entrada', 445.00, '2025-06-13 17:34:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 1)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-106'),
(377, 14, 'saida', 3782.50, '2025-06-13 17:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 106)'),
(378, 46, 'saida', 4005.00, '2025-06-13 17:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 106)'),
(379, 47, 'saida', 35600.00, '2025-06-13 17:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 106)'),
(380, 45, 'entrada', 446.00, '2025-06-13 17:34:00', 'Produção OP: 2506401132 (Máquina ID: 5, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506401132-107'),
(381, 14, 'saida', 3791.00, '2025-06-13 17:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 107)'),
(382, 46, 'saida', 4014.00, '2025-06-13 17:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 107)'),
(383, 47, 'saida', 35680.00, '2025-06-13 17:34:00', 'Consumo OP: 2506401132 (Prod. Acabado: 997514262371)', 'Consumo de material para produção (Apontamento ID: 107)'),
(384, 45, 'saida', 446.00, '2025-06-13 20:39:19', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 107'),
(385, 45, 'saida', 445.00, '2025-06-13 20:39:22', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 106'),
(386, 45, 'saida', 9.00, '2025-06-13 20:39:47', 'Remoção Apontamento OP: 2506401132 (Máquina ID: 5, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 105'),
(387, 4, 'entrada', 100.00, '2025-06-13 12:52:00', 'Produção OP: 2506637402 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506637402-108'),
(388, 43, 'saida', 180.00, '2025-06-13 12:52:00', 'Consumo OP: 2506637402 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 108)'),
(389, 4, 'entrada', 500.00, '2025-06-13 12:52:00', 'Produção OP: 2506637402 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506637402-109'),
(390, 43, 'saida', 900.00, '2025-06-13 12:52:00', 'Consumo OP: 2506637402 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 109)'),
(391, 4, 'entrada', 399.00, '2025-06-14 12:54:00', 'Produção OP: 2506637402 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506637402-110'),
(392, 43, 'saida', 718.20, '2025-06-14 12:54:00', 'Consumo OP: 2506637402 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 110)'),
(393, 4, 'entrada', 2001.00, '2025-06-14 13:02:00', 'Produção OP: 2506637402 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506637402-111'),
(394, 43, 'saida', 3601.80, '2025-06-14 13:02:00', 'Consumo OP: 2506637402 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 111)'),
(395, 4, 'entrada', 3200.00, '2025-06-12 13:04:00', 'Produção OP: 2506874801 (Máquina ID: 11, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506874801-112'),
(396, 43, 'saida', 5760.00, '2025-06-12 13:04:00', 'Consumo OP: 2506874801 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 112)'),
(397, 47, 'ajuste', 27000.00, '2025-06-14 15:47:00', 'Acerto', ''),
(398, 47, 'saida', 30000.00, '2025-06-14 15:48:00', 'Acerto', ''),
(399, 47, 'saida', 270000.00, '2025-06-14 15:50:00', 'Acerto', ''),
(400, 47, 'ajuste', 160.00, '2025-06-14 15:52:00', 'Acerto', ''),
(401, 4, 'saida', 6000.00, '2025-06-14 16:00:00', 'Acerto', ''),
(402, 1, 'entrada', 20.00, '2025-06-16 16:03:00', 'Produção OP: 2506872118 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506872118-113'),
(403, 44, 'saida', 36.00, '2025-06-16 16:03:00', 'Consumo OP: 2506872118 (Prod. Acabado: 998072744635)', 'Consumo de material para produção (Apontamento ID: 113)'),
(404, 1, 'entrada', 15.00, '2025-06-17 16:04:00', 'Produção OP: 2506872118 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506872118-114'),
(405, 44, 'saida', 27.00, '2025-06-17 16:04:00', 'Consumo OP: 2506872118 (Prod. Acabado: 998072744635)', 'Consumo de material para produção (Apontamento ID: 114)'),
(406, 1, 'saida', 15.00, '2025-06-14 19:15:53', 'Remoção Apontamento OP: 2506872118 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 114'),
(407, 1, 'saida', 20.00, '2025-06-14 19:15:56', 'Remoção Apontamento OP: 2506872118 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 113'),
(408, 45, 'saida', 5804.00, '2025-06-14 16:46:00', 'Acerto', ''),
(409, 4, 'entrada', 13000.00, '2025-06-16 17:31:00', 'Produção OP: 2506736708 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506736708-115'),
(410, 43, 'saida', 35.10, '2025-06-16 17:31:00', 'Consumo OP: 2506736708 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 115)'),
(411, 4, 'entrada', 13500.00, '2025-06-17 17:31:00', 'Produção OP: 2506736708 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506736708-116'),
(412, 43, 'saida', 36.45, '2025-06-17 17:31:00', 'Consumo OP: 2506736708 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 116)'),
(413, 4, 'entrada', 1164.00, '2025-06-18 17:31:00', 'Produção OP: 2506736708 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506736708-117'),
(414, 43, 'saida', 3.14, '2025-06-18 17:31:00', 'Consumo OP: 2506736708 (Prod. Acabado: 860456457991)', 'Consumo de material para produção (Apontamento ID: 117)'),
(415, 4, 'saida', 13000.00, '2025-06-14 20:43:42', 'Remoção Apontamento OP: 2506736708 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 115'),
(416, 4, 'saida', 13500.00, '2025-06-14 20:43:45', 'Remoção Apontamento OP: 2506736708 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 116'),
(417, 4, 'saida', 1164.00, '2025-06-14 20:44:13', 'Remoção Apontamento OP: 2506736708 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 117'),
(418, 43, 'entrada', 100.00, '2025-06-14 18:00:00', 'NF: 321564 (Fornecedor: Global Logistics)', ''),
(419, 1, 'entrada', 15561.00, '2025-06-16 18:07:00', 'Produção OP: 2506148450 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506148450-118'),
(420, 44, 'saida', 728.25, '2025-06-16 18:07:00', 'Consumo OP: 2506148450 (Prod. Acabado: 998072744635)', 'Consumo de material para produção (Apontamento ID: 118)'),
(421, 44, 'desempenho', 728.24, '2025-06-14 17:50:00', 'Desempenho Manual OP: 2506148450 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506148450'),
(422, 44, 'desempenho', 728.24, '2025-06-14 17:50:00', 'Desempenho Manual OP: 2506148450 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506148450'),
(423, 1, 'saida', 15561.00, '2025-06-14 21:23:48', 'Remoção Apontamento OP: 2506148450 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 118'),
(424, 44, 'desempenho', 728.20, '2025-06-14 17:50:00', 'Desempenho Manual OP: 2506148450 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506148450'),
(425, 44, 'desempenho', 728.10, '2025-06-14 17:50:00', 'Desempenho Manual OP: 2506148450 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506148450'),
(426, 44, 'desempenho', 728.10, '2025-06-14 17:50:00', 'Desempenho Manual OP: 2506148450 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506148450'),
(427, 44, 'desempenho', 728.09, '2025-06-14 17:50:00', 'Desempenho Manual OP: 2506148450 (Resp: Jurandir Prestes (M001))', 'Empenho automático para OP 2506148450'),
(428, 1, 'entrada', 15561.00, '2025-06-16 19:13:00', 'Produção OP: 2506148450 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506148450-119'),
(429, 44, 'saida', 728.25, '2025-06-16 19:13:00', 'Consumo OP: 2506148450 (Prod. Acabado: 998072744635)', 'Consumo de material para produção (Apontamento ID: 119)'),
(430, 1, 'entrada', 15561.00, '2025-06-17 19:13:00', 'Produção OP: 2506148450 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506148450-120'),
(431, 44, 'saida', 728.25, '2025-06-17 19:13:00', 'Consumo OP: 2506148450 (Prod. Acabado: 998072744635)', 'Consumo de material para produção (Apontamento ID: 120)'),
(432, 4, 'entrada', 12332.00, '2025-06-14 19:29:00', 'Produção OP: 2506473569 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506473569-121'),
(433, 4, 'saida', 12332.00, '2025-06-14 22:30:51', 'Remoção Apontamento OP: 2506473569 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 121'),
(434, 4, 'entrada', 13832.00, '2025-06-14 19:30:00', 'Produção OP: 2506473569 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506473569-122'),
(435, 4, 'entrada', 13832.00, '2025-06-14 19:42:00', 'Produção OP: 2506473569 (Máquina ID: 9, Operador ID: 5)', 'Entrada de produto acabado por apontamento. Lote: 2506473569-123'),
(436, 4, 'saida', 13832.00, '2025-06-14 22:46:05', 'Remoção Apontamento OP: 2506473569 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 122'),
(437, 4, 'saida', 13832.00, '2025-06-14 22:47:24', 'Remoção Apontamento OP: 2506473569 (Máquina ID: 9, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 123'),
(438, 4, 'entrada', 13832.00, '2025-06-16 19:53:00', 'Produção OP: 2506473569', 'Entrada de produto acabado por apontamento. Lote: 2506473569-124'),
(439, 4, 'entrada', 13832.00, '2025-06-17 20:15:00', 'Produção OP: 2506960359', 'Entrada por apontamento. Lote: 2506960359-125'),
(440, 4, 'entrada', 13830.00, '2025-06-18 20:16:00', 'Produção OP: 2506960359', 'Entrada por apontamento. Lote: 2506960359-126'),
(441, 4, 'entrada', 10000.00, '2025-06-14 20:23:00', 'Produção OP: 2506960359', 'Entrada por apontamento. Lote: 2506960359-127'),
(442, 4, 'entrada', 18832.00, '2025-06-14 20:31:00', 'Produção OP: 2506960359', 'Entrada por apontamento. Lote: 2506960359-128'),
(443, 4, 'entrada', 18832.00, '2025-06-18 20:37:00', 'Produção OP: 2506960359', 'Entrada por apontamento. Lote: 2506960359-129'),
(444, 4, 'entrada', 18832.00, '2025-06-14 20:37:00', 'Produção OP: 2506960359', 'Entrada por apontamento. Lote: 2506960359-130'),
(445, 14, 'entrada', 10400.00, '2025-06-18 20:43:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-131'),
(446, 14, 'entrada', 10400.00, '2025-06-18 20:46:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-132'),
(447, 46, 'entrada', 1.00, '2025-06-14 20:52:00', 'Produção OP: 2506480978', 'Entrada por apontamento. Lote: 2506480978-133'),
(448, 46, 'saida', 1.00, '2025-06-14 23:52:45', 'Remoção Apontamento OP: 2506480978 (Máquina ID: 11, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 133'),
(449, 14, 'entrada', 10400.00, '2025-06-14 20:54:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-134'),
(450, 14, 'entrada', 5.00, '2025-06-14 20:54:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-135'),
(451, 14, 'saida', 5.00, '2025-06-14 23:55:56', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 135'),
(452, 14, 'saida', 10400.00, '2025-06-14 23:56:22', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 134'),
(453, 14, 'entrada', 10400.00, '2025-06-14 20:56:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-136'),
(454, 14, 'saida', 10400.00, '2025-06-14 23:57:09', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 136'),
(455, 14, 'entrada', 10400.00, '2025-06-14 21:05:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-137'),
(456, 14, 'entrada', 10000.00, '2025-06-14 21:06:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-138'),
(457, 14, 'saida', 10000.00, '2025-06-15 00:06:32', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Carlos Silva (M005)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 138'),
(458, 14, 'saida', 10400.00, '2025-06-15 00:09:07', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 137'),
(459, 14, 'entrada', 10400.00, '2025-06-14 21:10:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-139'),
(460, 14, 'saida', 10400.00, '2025-06-15 00:10:51', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 139'),
(462, 14, 'entrada', 10400.00, '2025-06-14 21:18:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-141'),
(463, 14, 'saida', 10400.00, '2025-06-15 00:19:15', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Fernando Alves (M004)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 141'),
(464, 14, 'entrada', 10400.00, '2025-06-14 21:19:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-142'),
(465, 14, 'saida', 10400.00, '2025-06-15 00:20:35', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Jurandir Prestes (M001)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 142'),
(466, 14, 'entrada', 10400.00, '2025-06-14 21:23:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-143'),
(467, 14, 'saida', 10400.00, '2025-06-15 00:24:50', 'Remoção Apontamento OP: 2506747004 (Máquina ID: 4, Operador: Mariana Dantas (M002)) [EXCLUÍDO]', 'Remoção de apontamento de produção ID: 143'),
(471, 14, 'entrada', 10400.00, '2025-06-14 21:34:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-147'),
(472, 14, 'entrada', 10400.00, '2025-06-18 21:34:00', 'Produção OP: 2506747004', 'Entrada por apontamento. Lote: 2506747004-148'),
(473, 14, 'saida', 10400.00, '2025-06-14 21:43:28', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 147'),
(474, 14, 'saida', 10400.00, '2025-06-14 21:52:14', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 147'),
(475, 14, 'entrada', 10400.00, '2025-06-14 21:52:58', 'Estorno Consumo', 'Estorno do Consumo ID: 22'),
(476, 14, 'saida', 10400.00, '2025-06-14 21:57:33', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 147'),
(477, 14, 'entrada', 10400.00, '2025-06-14 21:59:14', 'Estorno Consumo', 'Estorno do Consumo ID: 23'),
(478, 4, 'saida', 18832.00, '2025-06-14 22:21:04', 'Consumo Produção', 'Consumo referente ao lote do Apontamento ID: 129'),
(479, 4, 'saida', 18832.00, '2025-06-14 22:36:14', 'Consumo Produção', 'Consumo referente ao lote: 2506960359-130'),
(480, 4, 'entrada', 18832.00, '2025-06-14 22:36:34', 'Estorno Consumo', 'Estorno do Consumo ID: 25'),
(481, 4, 'saida', 18000.00, '2025-06-14 22:37:07', 'Consumo Produção', 'Consumo referente ao lote: 2506960359-130'),
(483, 4, 'entrada', 18000.00, '2025-06-14 23:08:38', 'Estorno Consumo', 'Estorno do Consumo ID: 26'),
(484, 4, 'saida', 10000.00, '2025-06-14 23:10:18', 'Consumo Produção', 'Consumo referente ao lote: 2506960359-130'),
(485, 4, 'entrada', 10000.00, '2025-06-14 23:11:12', 'Estorno Consumo', 'Estorno do Consumo ID: 27'),
(486, 4, 'saida', 10000.00, '2025-06-14 23:12:07', 'Consumo Produção', 'Consumo referente ao lote: 2506960359-130'),
(487, 4, 'saida', 8832.00, '2025-06-14 23:13:54', 'Consumo Produção', 'Consumo referente ao lote: 2506960359-DEV130'),
(488, 4, 'entrada', 8832.00, '2025-06-14 23:14:17', 'Estorno Consumo', 'Estorno do Consumo ID: 29'),
(489, 4, 'entrada', 10000.00, '2025-06-14 23:14:50', 'Estorno Consumo', 'Estorno do Consumo ID: 28'),
(490, 4, 'saida', 10000.00, '2025-06-14 23:15:59', 'Consumo Produção', 'Consumo referente ao lote: 2506960359-130'),
(491, 4, 'saida', 4000.00, '2025-06-14 23:16:41', 'Consumo Produção', 'Consumo referente ao lote: 2506960359-DEV130'),
(492, 4, 'entrada', 4000.00, '2025-06-14 23:17:21', 'Estorno Consumo', 'Estorno do Consumo ID: 31'),
(493, 4, 'saida', 8832.00, '2025-06-14 23:17:37', 'Consumo Produção', 'Consumo referente ao lote: 2506960359-DEV130'),
(494, 43, 'entrada', 100.00, '2025-06-14 23:24:00', '', ''),
(495, 43, 'saida', 100.00, '2025-06-14 23:51:30', 'Produção', 'Consumo de Matéria-Prima para OP'),
(496, 43, 'entrada', 100.00, '2025-06-14 23:51:43', 'Estorno Matéria-Prima', 'Estorno do Consumo de Matéria-Prima ID: 33'),
(497, 43, 'saida', 100.00, '2025-06-14 23:51:58', 'Produção', 'Consumo de Matéria-Prima para OP'),
(498, 43, 'saida', 100.00, '2025-06-14 23:55:55', 'Produção', 'Consumo de Matéria-Prima para OP'),
(499, 43, 'entrada', 100.00, '2025-06-14 23:56:10', 'Estorno Matéria-Prima', 'Estorno do Consumo de Matéria-Prima ID: 34'),
(500, 43, 'saida', 20.00, '2025-06-14 23:56:38', 'Produção', 'Consumo de Matéria-Prima para OP'),
(501, 44, 'entrada', 2000.00, '2025-06-14 23:58:00', 'NF: 456783 (Fornecedor: Mega Distribuidores)', ''),
(502, 44, 'saida', 1500.00, '2025-06-14 23:59:17', 'Produção', 'Consumo de Matéria-Prima para OP'),
(503, 46, 'entrada', 23000.00, '2025-06-15 00:01:00', 'Produção OP: 2506480978', 'Entrada por apontamento. Lote: 2506480978-155'),
(504, 1, 'saida', 15561.00, '2025-06-15 00:07:38', 'Consumo Produção', 'Consumo referente ao lote: 2506148450-119'),
(505, 1, 'saida', 15561.00, '2025-06-15 00:08:19', 'Consumo Produção', 'Consumo referente ao lote: 2506148450-120'),
(506, 46, 'entrada', 400.00, '2025-06-15 00:08:00', 'Produção OP: 2506480978', 'Entrada por apontamento. Lote: 2506480978-156'),
(508, 45, 'entrada', 800.00, '2025-06-15 00:14:00', 'Produção OP: 2506269286', 'Entrada por apontamento. Lote: 2506269286-158'),
(509, 45, 'entrada', 800.00, '2025-06-15 00:17:00', 'Produção OP: 2506269286', 'Entrada por apontamento. Lote: 2506269286-159'),
(510, 46, 'saida', 23000.00, '2025-06-15 00:19:36', 'Consumo Produção', 'Consumo referente ao lote: 2506480978-155'),
(511, 14, 'saida', 10400.00, '2025-06-15 00:22:26', 'Consumo Produção', 'Consumo referente ao lote: 2506747004-147'),
(512, 14, 'saida', 10400.00, '2025-06-15 00:22:35', 'Consumo Produção', 'Consumo referente ao lote: 2506747004-148'),
(513, 45, 'entrada', 990.00, '2025-06-16 00:23:00', 'Produção OP: 2506269286', 'Entrada por apontamento. Lote: 2506269286-160'),
(514, 47, 'saida', 650.00, '2025-06-15 00:24:12', 'Produção', 'Consumo de Matéria-Prima para OP'),
(515, 45, 'entrada', 10.00, '2025-06-16 00:24:00', 'Produção OP: 2506269286', 'Entrada por apontamento. Lote: 2506269286-161');

-- --------------------------------------------------------

--
-- Estrutura para tabela `operadores`
--

CREATE TABLE `operadores` (
  `id` int(11) NOT NULL,
  `matricula` varchar(20) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `password_hash` varchar(255) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `localizacao` varchar(100) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `operadores`
--

INSERT INTO `operadores` (`id`, `matricula`, `username`, `cargo`, `ativo`, `password_hash`, `nome`, `localizacao`, `deleted_at`) VALUES
(1, 'M005', 'carlos', 'Apontador', 1, '$2y$10$mqcjAFJcPSboQiG9f6pBcOxuEzCad3xOHkwlBiKq36txqoTuxhflK', 'Carlos Silva', 'usuario', NULL),
(2, 'M002', 'mariana', 'Apontador', 1, NULL, 'Mariana Dantas', 'usuario', NULL),
(3, 'M003', 'rafael', 'Analista', 1, '$2y$10$D5mC7kXp2G212VeX.WWH5eSoPU58CnqX.UGf/v8TpAEEC0ZyZajRm', 'Rafael', 'usuario', NULL),
(4, 'M004', 'fernando', 'Apontador', 1, NULL, 'Fernando Alves', 'usuario', NULL),
(5, 'M001', 'jurandir', 'admin', 1, '$2y$10$qi3daBeWh.dHECMxTpilLuEK7oCHNB9BIV38SF6EmozUtX7bjWrve', 'Jurandir Prestes', 'pcp', NULL),
(7, 'M006', 'kelly', 'Analista', 1, '$2y$10$XH0zYJNkWd5gL/DoM9fpduXIFk2fECHI4K/Jf6Y0KPBlZZdwMGpQq', 'Rosekelly Cirineu', 'usuario', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `ordens_producao`
--

CREATE TABLE `ordens_producao` (
  `id` int(11) NOT NULL,
  `numero_op` varchar(50) NOT NULL,
  `numero_pedido` varchar(50) DEFAULT NULL,
  `produto_id` int(11) NOT NULL,
  `maquina_id` int(11) DEFAULT NULL,
  `quantidade_produzir` decimal(10,2) NOT NULL,
  `data_emissao` date NOT NULL,
  `data_prevista_conclusao` date DEFAULT NULL,
  `data_conclusao` datetime DEFAULT NULL,
  `status` enum('pendente','em_producao','concluida','cancelada') NOT NULL DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `ordens_producao`
--

INSERT INTO `ordens_producao` (`id`, `numero_op`, `numero_pedido`, `produto_id`, `maquina_id`, `quantidade_produzir`, `data_emissao`, `data_prevista_conclusao`, `data_conclusao`, `status`, `observacoes`, `deleted_at`) VALUES
(1, '250001', 'PO25067579', 33, 11, 3750.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '10 caixas de 375 peças', '2025-05-31 00:00:00'),
(2, '2506757719', 'PO25067519', 33, 11, 2850.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'em_producao', '850', '2025-05-31 00:00:00'),
(3, '2506354138', 'PO250675771', 33, 5, 2000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'em_producao', '2000', '2025-05-31 00:00:00'),
(4, '2506942167', 'PO25067519', 32, 2, 999.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(5, '2506006726', 'PO25067519', 32, 5, 99.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '9', '2025-05-31 00:00:00'),
(6, '2506119190', 'PO25067519', 32, 2, 999.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'em_producao', '', '2025-05-31 00:00:00'),
(7, '2506947387', 'PO25067519', 30, 2, 99.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '9', '2025-05-31 00:00:00'),
(8, '2506521602', 'PO25067519', 30, 5, 99.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(9, '2506200823', 'PO25067519', 32, 5, 88.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(10, '2506688101', 'PO250675771', 32, 5, 88.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(11, '2506325723', 'PO250675771', 33, 11, 76.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(12, '2506607049', 'PO25067519', 30, 5, 66.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'em_producao', '66', '2025-05-31 00:00:00'),
(13, '2506083330', 'PO250675771', 33, 11, 999.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '999', '2025-05-31 00:00:00'),
(14, '2506296349', '', 32, 2, 1.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(15, '2506855997', 'PO25067519', 38, 5, 777.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'em_producao', '', '2025-05-31 00:00:00'),
(16, '2506356118', '0706251706', 33, 2, 1900.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(17, '2506659847', '0706251706', 38, 11, 88.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(18, '2506102927', '0806250106', 45, 5, 2000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', 'uma garga', '2025-05-31 00:00:00'),
(19, '2506096008', '0806251306', 45, 5, 500.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(20, '2506514720', '0706251706', 45, 5, 1000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(21, '2506786656', '0706251706', 45, 5, 1000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(22, '2506353264', '0706251706', 14, 9, 2200.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(23, '2506868668', '0706251706', 14, 11, 2100.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(24, '2506595644', '0706251706', 4, 11, 2800.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(25, '2506725474', '#0806251518', 45, 5, 1000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(26, '2506218500', '#0806251518', 45, 5, 5000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(27, '2506862308', '#0806251518', 46, 11, 5000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(28, '2506285156', '0706251706', 36, 2, 3333.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(29, '2506037382', '#0806251518', 4, 11, 300.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(30, '2506967993', '0706251706', 45, 5, 1100.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(31, '2506517118', '#0806251518', 45, 5, 6000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(32, '2506704381', '#0806251518', 45, 5, 2800.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(33, '2506363416', '#0806251518', 45, 5, 555.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '3', '2025-05-31 00:00:00'),
(34, '2506874303', '#0806251518', 45, 5, 333.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(35, '2506156257', '#0806251518', 45, 5, 2000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '2506156257', '2025-05-31 00:00:00'),
(36, '2506189834', '#0806251518', 45, 5, 750.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(37, '2506994029', '0706251706', 45, 5, 2500.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(38, '2506401132', 'PO25067579', 45, 5, 1600.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(39, '2506943935', '0706251706', 14, 2, 0.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(40, '2506555475', 'PO25067519', 46, 13, 0.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'cancelada', '', '2025-05-31 00:00:00'),
(41, '2506083960', '0806251306', 46, 11, 0.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(42, '2506342176', '#0806251518', 12, 5, 1.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(43, '2506133713', '0706251706', 12, 9, 1111.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'pendente', '', '2025-05-31 00:00:00'),
(44, '2506637402', '0706251706', 4, 11, 3000.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(45, '2506874801', '1406251306', 4, 11, 3200.00, '2025-05-26', '2025-05-30', '2025-05-31 00:00:00', 'concluida', '', '2025-05-31 00:00:00'),
(46, '2506527204', '250620251520', 45, 5, 2600.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 16:23:19'),
(47, '2506883950', '1406251306', 46, 11, 25.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 16:23:18'),
(48, '2506872118', '1406251306', 1, 9, 0.00, '2025-06-14', '2025-06-21', NULL, 'cancelada', '', '2025-06-14 16:20:33'),
(49, '2506064172', '1406251306', 14, 4, 30.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 16:23:16'),
(50, '2506976449', '1406251306', 1, 9, 36.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 16:23:14'),
(51, '2506269286', '1406251306', 45, 5, 2600.00, '2025-06-14', '2025-06-21', '2025-06-15 00:24:48', 'concluida', '', NULL),
(52, '2506132206', '1406251306', 14, 4, 2800.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 17:19:12'),
(53, '2506747004', '1406251306', 14, 4, 20800.00, '2025-06-14', '2025-06-21', '2025-06-14 21:35:09', 'concluida', '', NULL),
(54, '2506480978', '1406251306', 46, 11, 23400.00, '2025-06-14', '2025-06-21', '2025-06-15 00:09:02', 'concluida', '', NULL),
(55, '2506638740', '1406251306', 4, 9, 27664.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 17:26:10'),
(56, '2506116740', '1406251306', 4, 9, 27664.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 17:28:18'),
(57, '2506736708', '1406251306', 4, 9, 0.00, '2025-06-14', '2025-06-21', NULL, 'cancelada', '', '2025-06-14 17:44:29'),
(58, '2506787821', '1406251306', 14, 4, 20800.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 17:55:56'),
(59, '2506473569', '1406251306', 4, 9, 0.00, '2025-06-14', '2025-06-21', NULL, 'cancelada', '', '2025-06-14 20:01:21'),
(60, '2506148450', '1406251306', 1, 9, 31122.00, '2025-06-14', '2025-06-21', '2025-06-14 19:14:36', 'concluida', '', NULL),
(61, '2506960359', '1406251306', 4, 9, 37664.00, '2025-06-14', '2025-06-21', '2025-06-14 20:38:23', 'concluida', '', NULL),
(62, '2506024611', '2125067577', 43, 11, 1000.00, '2025-06-14', '2025-06-21', NULL, 'pendente', '', '2025-06-14 20:40:11');

--
-- Acionadores `ordens_producao`
--
DELIMITER $$
CREATE TRIGGER `after_ordens_producao_update_reempenho` AFTER UPDATE ON `ordens_producao` FOR EACH ROW BEGIN
    -- Declaração de variáveis que serão usadas no gatilho
    DECLARE v_total_produzido DECIMAL(10, 2) DEFAULT 0;
    DECLARE v_quantidade_restante DECIMAL(10, 2) DEFAULT 0;

    -- Condição principal: O gatilho só é executado se o status foi alterado
    -- e o NOVO status NÃO for 'concluida' nem 'cancelada'.
    -- Isto é útil, por exemplo, se uma OP concluída for reaberta.
    IF OLD.status <> NEW.status AND NEW.status NOT IN ('concluida', 'cancelada') THEN

        -- 1. Calcula o total que já foi produzido para esta OP
        SELECT SUM(COALESCE(quantidade_produzida, 0))
        INTO v_total_produzido
        FROM apontamentos_producao
        WHERE ordem_producao_id = NEW.id AND deleted_at IS NULL;

        -- 2. Calcula a quantidade que ainda falta produzir
        SET v_quantidade_restante = NEW.quantidade_produzir - v_total_produzido;

        -- 3. Apenas continua se a quantidade restante for positiva
        IF v_quantidade_restante > 0 THEN

            -- 4. REATIVA E ATUALIZA OS EMPENHOS EXISTENTES
            -- Em vez de inserir novos, este gatilho agora encontra os empenhos
            -- que foram marcados como deletados e os reativa, ajustando a quantidade.
            UPDATE empenho_materiais em
            JOIN lista_materiais lm ON em.produto_id = lm.produto_filho_id
            SET
                em.quantidade_empenhada = (lm.quantidade_necessaria * v_quantidade_restante),
                em.deleted_at = NULL  -- Reativa o empenho
            WHERE
                em.ordem_producao_id = NEW.id
                AND lm.produto_pai_id = NEW.produto_id;


            -- 5. ATUALIZA O ESTOQUE EMPENHADO GLOBAL NA TABELA DE PRODUTOS
            -- Adiciona a quantidade recém-empenhada de volta ao estoque empenhado de cada produto.
            UPDATE produtos p
            JOIN lista_materiais lm ON p.id = lm.produto_filho_id
            SET
                p.estoque_empenhado = p.estoque_empenhado + (lm.quantidade_necessaria * v_quantidade_restante)
            WHERE
                lm.produto_pai_id = NEW.produto_id
                AND lm.deleted_at IS NULL;

        END IF; -- Fim da verificação de v_quantidade_restante > 0
    END IF; -- Fim da condição principal do gatilho
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_ordens_producao_status_update` BEFORE UPDATE ON `ordens_producao` FOR EACH ROW BEGIN
    -- Este gatilho é acionado ANTES de uma atualização na tabela ordens_producao.
    -- Ele ajusta NEW.quantidade_produzir e gerencia os empenhos quando o status muda para 'concluida' ou 'cancelada'.

    -- Variável para armazenar a soma da quantidade produzida
    DECLARE total_apontado_para_op DECIMAL(10,2);

    -- Verifica se o status está sendo alterado para 'concluida' ou 'cancelada'
    -- E se o status ANTERIOR não era já 'concluida' ou 'cancelada' (para evitar re-cálculo desnecessário)
    IF NEW.status IN ('concluida', 'cancelada') AND OLD.status NOT IN ('concluida', 'cancelada') THEN
        -- A. Ajusta NEW.quantidade_produzir para a soma dos apontamentos
        SELECT SUM(COALESCE(ap.quantidade_produzida, 0))
        INTO total_apontado_para_op
        FROM apontamentos_producao ap
        WHERE ap.ordem_producao_id = NEW.id AND ap.deleted_at IS NULL;

        SET total_apontado_para_op = COALESCE(total_apontado_para_op, 0);
        SET NEW.quantidade_produzir = total_apontado_para_op;

        -- B. Ajusta os empenhos relacionados a esta OP (libera os empenhos restantes)
        -- Passo B1: Atualiza 'estoque_empenhado' na tabela 'produtos'
        -- Percorre todos os empenhos ATIVOS para esta OP e subtrai a quantidade empenhada do estoque_empenhado do produto.
        UPDATE produtos p
        JOIN empenho_materiais em ON p.id = em.produto_id
        SET p.estoque_empenhado = GREATEST(0, p.estoque_empenhado - em.quantidade_empenhada)
        WHERE em.ordem_producao_id = NEW.id
        AND em.deleted_at IS NULL; -- Apenas empenhos ativos

        -- Passo B2: Soft delete os registros de empenho_materiais para esta OP
        -- Marca como deletado e zera a quantidade empenhada para fins de clareza
        UPDATE empenho_materiais
        SET deleted_at = NOW(),
            quantidade_empenhada = 0 -- Zera a quantidade empenhada, pois o empenho foi "liberado"
        WHERE ordem_producao_id = NEW.id
        AND deleted_at IS NULL; -- Apenas empenhos ativos

    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos_venda_lookup`
--

CREATE TABLE `pedidos_venda_lookup` (
  `id` int(11) NOT NULL,
  `numero_pedido` varchar(50) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `pedidos_venda_lookup`
--

INSERT INTO `pedidos_venda_lookup` (`id`, `numero_pedido`, `descricao`) VALUES
(1, '2125067577', 'Pedido do Cliente ABC - 1000 peças de Parafuso'),
(2, '2425067579', 'Pedido do Cliente XYZ - 50 chapas de alumínio'),
(3, '2325067519', 'Pedido Urgente do Cliente DEF - 500 sensores'),
(37, '0706251706', NULL),
(44, '0806250106', NULL),
(45, '0806251306', NULL),
(65, '0806251518', NULL),
(197, '1406251306', NULL),
(201, '250620251520', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(40) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `descricao` varchar(40) NOT NULL,
  `unidade_medida` varchar(10) NOT NULL,
  `estoque_minimo` decimal(10,2) DEFAULT NULL,
  `estoque_atual` decimal(10,2) DEFAULT NULL,
  `estoque_empenhado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grupo` varchar(30) NOT NULL,
  `subgrupo` varchar(30) NOT NULL,
  `modelo` varchar(20) DEFAULT NULL,
  `acabamento` varchar(20) DEFAULT NULL,
  `familia` varchar(20) DEFAULT NULL,
  `desenho` varchar(20) DEFAULT NULL,
  `velocidade` decimal(10,2) NOT NULL,
  `perimetro_mm` decimal(10,2) DEFAULT NULL,
  `area_perfil_mm2` decimal(10,2) DEFAULT NULL,
  `codigo2` varchar(20) DEFAULT NULL,
  `unidade_medida2` varchar(5) DEFAULT NULL,
  `espessura` decimal(10,2) NOT NULL,
  `largura` decimal(10,2) NOT NULL,
  `comprimento` decimal(10,2) DEFAULT NULL,
  `altura_embalagem` int(11) DEFAULT NULL,
  `largura_embalagem` int(11) DEFAULT NULL,
  `pecas_por_embalagem` int(11) DEFAULT NULL,
  `pecas_por_fardo` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `nome`, `codigo`, `descricao`, `unidade_medida`, `estoque_minimo`, `estoque_atual`, `estoque_empenhado`, `grupo`, `subgrupo`, `modelo`, `acabamento`, `familia`, `desenho`, `velocidade`, `perimetro_mm`, `area_perfil_mm2`, `codigo2`, `unidade_medida2`, `espessura`, `largura`, `comprimento`, `altura_embalagem`, `largura_embalagem`, `pecas_por_embalagem`, `pecas_por_fardo`, `deleted_at`) VALUES
(1, 'Madeira Serrada A 100x100x2600', '998072744635', 'Madeira Serrada A 100x100x2600', 'PC', 15.00, 0.00, 0.00, 'Serrados', 'A', '', 'Bruto', 'Palete', '', 20.00, 0.00, 0.00, '', 'M3', 100.00, 100.00, 2600.00, 25, 12, 300, 10, NULL),
(2, 'Madeira Serrada B 15x120x1200', '089589886634', 'Madeira Serrada B 15x120x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Bruto', 'Palete', '', 4.00, 0.00, 0.00, '', '', 15.00, 120.00, 1200.00, 0, 0, 0, 0, NULL),
(3, 'Madeira Serrada A 15x90x1200', '056226383889', 'Madeira Serrada A 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Bruto', 'Tabique', '', 4.00, 0.00, 0.00, '', '', 15.00, 90.00, 1200.00, 25, 14, 350, 0, NULL),
(4, 'Madeira Serrada A 17x90x1000', '860456457991', 'Madeira Serrada A 17x90x1000', 'PC', 1000.00, 0.00, 0.00, 'Serrados', 'A', '', 'Bruto', 'Palete', '', 4.00, 0.00, 0.00, '', 'M3', 17.00, 90.00, 1000.00, 25, 15, 375, 0, NULL),
(5, 'Madeira Serrada B 15x90x1200', '039859349152', 'Madeira Serrada B 15x90x1200', 'M3', 100.00, 88.00, 0.00, 'Serrados', 'B', '', 'Bruto', 'Palete', '', 4.00, 0.00, 0.00, '', '', 15.00, 90.00, 1200.00, 25, 12, 300, 0, NULL),
(6, 'Madeira Serrada B 15x70x1200', '628892879852', 'Madeira Serrada B 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Bruto', 'Palete', '', 4.00, 0.00, 0.00, '', '', 15.00, 70.00, 1200.00, 25, 14, 350, 0, NULL),
(7, 'Madeira Serrada C 15x70x1200', '138808764334', 'Madeira Serrada C 15x70x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'C', '', 'Bruto', 'Palete', '', 4.00, 0.00, 0.00, '', '', 15.00, 70.00, 1200.00, 0, 0, 0, 0, NULL),
(8, 'Madeira Serrada C 15x90x1200', '201388344624', 'Madeira Serrada C 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'C', '', 'Bruto', 'Palete', '', 4.00, 0.00, 0.00, '', '', 15.00, 90.00, 1200.00, 0, 0, 0, 0, NULL),
(9, 'Madeira Refilada A 30x30x1200', '519063057735', 'Madeira Refilada A 30x30x1200', 'M3', 50.00, 0.00, 0.00, 'Refilados', 'A', '', 'Aplainado', 'Tabique', '', 2.00, 0.00, 0.00, '', '', 30.00, 30.00, 1200.00, 0, 0, 0, 0, NULL),
(10, 'Madeira Refilada A 45x45x1200', '219458739724', 'Madeira Refilada A 45x45x1200', 'M3', 50.00, 0.00, 0.00, 'Refilados', 'A', '', 'Aplainado', 'Tabique', '', 3.00, 0.00, 0.00, '', '', 45.00, 45.00, 1200.00, 0, 0, 0, 0, NULL),
(11, 'Madeira Destopada A 45x45x450', '525647525154', 'Madeira Destopada A 45x45x450', 'M3', 50.00, 0.00, 0.00, 'Blocos', 'A', '', 'Aplainado', 'Palete', '', 3.00, 0.00, 0.00, '', '', 45.00, 45.00, 450.00, 0, 0, 0, 0, NULL),
(12, 'Madeira Refilada A 17x120x900', 'R98072744635', 'Madeira Serrada A 15x120x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'C', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 120.00, 900.00, 25, 12, 300, 10, NULL),
(13, 'Madeira Refilada B 17x120x900', 'R89589886634', 'Madeira Serrada B 15x120x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 120.00, 900.00, 0, 0, 0, 0, NULL),
(14, 'Madeira Refilada A 17x90x900', 'R56226383889', 'Madeira Serrada A 15x90x1200', 'PC', 15.00, 0.00, 0.00, 'Serrados', 'A', '', 'Aplainado', 'Tabique', '', 15.00, 0.00, 0.00, '', 'M3', 17.00, 90.00, 900.00, 25, 14, 350, 0, NULL),
(15, 'Madeira Refilada A 17x70x900', 'R60456457991', 'Madeira Serrada A 15x70x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 70.00, 900.00, 25, 15, 375, 0, NULL),
(16, 'Madeira Refilada B 17x90x900', 'R39859349152', 'Madeira Serrada B 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'B', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 90.00, 900.00, 25, 12, 300, 0, NULL),
(17, 'Madeira Refilada B 17x70x900', 'R28892879852', 'Madeira Serrada B 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 70.00, 900.00, 25, 14, 350, 0, NULL),
(18, 'Madeira Refilada C 17x70x900', 'R38808764334', 'Madeira Serrada C 15x70x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'C', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 70.00, 900.00, 0, 0, 0, 0, NULL),
(19, 'Madeira Refilada C 17x90x900', 'R01388344624', 'Madeira Serrada C 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'C', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 90.00, 900.00, 0, 0, 0, 0, NULL),
(20, 'Madeira Refilada A 17x120x900', 'RB8072744635', 'Madeira Serrada A 15x120x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'C', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 120.00, 900.00, 25, 12, 300, 10, NULL),
(21, 'Madeira Refilada B 17x120x900', 'RB9589886634', 'Madeira Serrada B 15x120x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 120.00, 900.00, 0, 0, 0, 0, NULL),
(22, 'Madeira Refilada A 17x90x900', 'RB6226383889', 'Madeira Serrada A 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Aplainado', 'Tabique', '', 4.00, 0.00, 0.00, '', '', 17.00, 90.00, 900.00, 25, 14, 350, 0, NULL),
(23, 'Madeira Refilada A 17x70x900', 'RB0456457991', 'Madeira Serrada A 15x70x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 70.00, 900.00, 25, 15, 375, 0, NULL),
(24, 'Madeira Refilada B 17x90x900', 'RB9859349152', 'Madeira Serrada B 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'B', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 90.00, 900.00, 25, 12, 300, 0, NULL),
(25, 'Madeira Refilada B 17x70x900', 'RB8892879852', 'Madeira Serrada B 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'A', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 70.00, 900.00, 25, 14, 350, 0, NULL),
(26, 'Madeira Refilada C 17x70x900', 'RB8808764334', 'Madeira Serrada C 15x70x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'C', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 70.00, 900.00, 0, 0, 0, 0, NULL),
(27, 'Madeira Refilada C 17x90x900', 'RB1388344624', 'Madeira Serrada C 15x90x1200', 'M3', 100.00, 0.00, 0.00, 'Serrados', 'C', '', 'Aplainado', 'Palete', '', 4.00, 0.00, 0.00, '', '', 17.00, 90.00, 900.00, 0, 0, 0, 0, NULL),
(28, 'Placa Eletrônica Rev. A', '876543210987', 'Placa de controle principal', 'PC', 10.00, 50.00, 0.00, 'Componentes Eletrônicos', 'Circuitos Integrados', 'Standard', 'Bruto', 'Linha A', 'DES-001', 120.50, NULL, NULL, 'C2345', 'UND', 0.00, 0.00, 0.00, 2, 2, 4, 100, NULL),
(29, 'Eixo de Aço Carbono', '123456789012', 'Eixo para máquinas industriais', 'PC', 5.00, 20.00, 0.00, 'Peças Usinadas', 'Usinagem CNC', 'Industrial', 'Acabado', 'Linha B', 'DES-002', 300.00, 12.00, 5.00, 'A1234', 'KG', 0.00, 0.00, 0.00, 1, 1, 1, 50, NULL),
(30, 'Chapa de Alumínio 3mm', '987654321098', 'Chapa de Alumínio 500x500mm', 'M2', 2.00, 51.00, 0.00, 'Materiais Brutos', 'Metais', 'Standard', 'Acabado', 'Linha C', '', 50.00, 0.00, 0.00, 'X5678', 'M2', 3.00, 500.00, 500.00, 1, 1, 1, 10, NULL),
(31, 'Sensor de Proximidade', '456789012345', 'Sensor infravermelho de curto alcance', 'PC', 20.00, 80.00, 0.00, 'Componentes Eletrônicos', 'Sensores', 'Compacto', 'Acabado', 'Linha A', 'SNSR-01', 80.00, 0.00, 0.00, 'S9012', 'PC', 0.00, 0.00, 0.00, 5, 4, 20, 200, NULL),
(32, 'Caixa de Papelão Média', '012345678901', 'Caixa para transporte, 30x20x15 cm', 'PC', 50.00, 420.00, 0.00, 'Embalagens', 'Caixas de Papelão', 'Standard', 'Acabado', 'Componentes', '', 10.00, 0.00, 0.00, 'EMB001', 'PC', 0.00, 0.00, 0.00, 30, 20, 600, 6000, NULL),
(33, 'Conector Elétrico 3 Vias', '789012345678', 'Conector para fiação interna', 'PC', 30.00, 1449.00, 0.00, 'Componentes Eletrônicos', 'Conectores', 'Standard', 'Acabado', 'Linha B', 'CONN-05', 200.00, 0.00, 0.00, 'C3V', 'PC', 0.00, 0.00, 0.00, 10, 10, 100, 1000, NULL),
(34, 'Peça Plástica Injetada', '210987654321', 'Base plástica para montagem', 'PC', 15.00, 900.00, 0.00, 'Materiais Brutos', 'Plásticos', 'Industrial', 'Bruto', 'Linha C', 'PLAST-10', 150.00, NULL, NULL, 'PJT005', 'PC', 0.00, 0.00, 0.00, 5, 5, 25, 250, NULL),
(35, 'Palete de Madeira Padrão', '345678901234', 'Palete para transporte de cargas', 'PC', 5.00, 10.00, 0.00, 'Embalagens', 'Madeira', 'Standard', 'Bruto', 'Componentes', NULL, 5.00, NULL, NULL, 'PAL001', 'PC', 0.00, 0.00, 0.00, 1, 1, 1, 1, NULL),
(36, 'Rolamento Esférico', '654321098765', 'Rolamento de alta velocidade', 'PC', 25.00, 75.00, 0.00, 'Peças Usinadas', 'Usinagem CNC', 'Premium', 'Acabado', 'Especiais', 'ROL-03', 500.00, 0.00, 0.00, 'RLG001', 'PC', 0.00, 0.00, 0.00, 1, 1, 1, 10, NULL),
(37, 'Pino de Fixação', '098765432109', 'Pino para fixação de componentes', 'PC', 50.00, 200.00, 0.00, 'Peças Usinadas', 'Usinagem CNC', 'Standard', 'Acabado', 'Linha A', 'PIN-01', 800.00, 0.00, 0.00, 'FIX001', 'PC', 0.00, 0.00, 0.00, 20, 50, 1000, 5000, NULL),
(38, 'Fio de Cobre 2.5mm', '112233445566', 'Fio de cobre para eletrônica', 'M', 100.00, 848.00, 0.00, 'Materiais Brutos', 'Metais', 'Standard', 'Acabado', 'Linha B', '', 1000.00, 0.00, 0.00, 'FIO-001', 'KG', 2.50, 0.00, 1.00, 1, 1, 1, 1, NULL),
(39, 'Plástico Bolha Grande', '778899001122', 'Rolo de Plástico Bolha 1m x 50m', 'M2', 5.00, 15.00, 0.00, 'Embalagens', 'Plástico Bolha', 'Standard', 'Acabado', 'Componentes', '', 10.00, 0.00, 0.00, 'PLB002', 'M2', 1.00, 1000.00, 50000.00, 1, 1, 1, 1, NULL),
(40, 'Item para Excluir', '999999999999', 'Este item será marcado como excluído', 'PC', 1.00, 5.00, 0.00, 'Materiais Brutos', 'Metais', 'Standard', 'Bruto', 'Linha A', NULL, 10.00, NULL, NULL, 'EXCLUIR', 'PC', 0.00, 0.00, 0.00, 1, 1, 1, 1, '2025-06-05 10:00:00'),
(41, 'Produto Final PCP A', 'PF-PCP-001', 'Unidade montada principal do sistema PCP', 'PC', 5.00, 10.00, 0.00, 'Produtos Acabados', 'Montagem', 'Premium', 'Acabado', 'PCP Integrado', 'PCP-DES-A', 1.00, NULL, NULL, 'PF-ALT-001', 'CX', 0.00, 0.00, 0.00, 1, 1, 1, 1, NULL),
(42, 'Subconjunto Módulo Eletrônico', 'SUB-MOD-002', 'Módulo eletrônico pré-montado', 'PC', 2.00, 5.00, 0.00, 'Submontagens', 'Eletrônicos', 'Standard', 'Acabado', 'Eletrônica', 'MOD-DES-B', 0.50, NULL, NULL, 'SUB-ALT-002', 'CX', 0.00, 0.00, 0.00, 1, 1, 1, 5, NULL),
(43, 'Tora de Pinus Taeda S1 2600mm', '752235902497', 'Tora de Pinus Taeda S1 2600mm', 'TON', 5000.00, 280.00, 0.00, 'Toras', 'S1', 'Taeda', 'Bruto', 'Materia-Prima', '', 1.00, 0.00, 0.00, '', 'MST', 1.00, 1.00, 2600.00, 0, 0, 0, 0, NULL),
(44, 'Tora de Pinus Taeda  S2 2600mm', '086121636640', 'Tora de Pinus S2 2600mm', 'TON', 5000.00, 500.00, 0.00, 'Toras', 'S2', 'Taeda', 'Bruto', 'Materia-Prima', '', 1.00, 0.00, 0.00, '', '', 1.00, 1.00, 2600.00, 0, 0, 0, 0, NULL),
(45, 'Palete PBR 900x 1000 x 140', '997514262371', 'Palete PBR 900x 1000 x 140', 'PC', 1000.00, 2600.00, 0.00, 'Palete', 'Pinus', 'Standard', 'Acabado', 'PBR', '100', 30.00, 0.00, 0.00, '', 'M3', 140.00, 1000.00, 900.00, 0, 0, 0, 0, NULL),
(46, 'Bloco Pinus 100x100x100', '191494622171', 'Bloco Pinus 100x100x100', 'PC', 15.00, 400.00, 0.00, 'Blocos', 'A', 'Standard', 'Aplainado', 'Tabique', '', 15.00, 0.00, 0.00, '', 'M3', 100.00, 100.00, 100.00, 0, 0, 0, 0, NULL),
(47, 'Pregos anelados 17x27', '804600637540', 'Pregos anelados 17x27', 'KG', 5000.00, 11670.00, 0.00, 'Pregos', 'Metais', 'Standard', '', 'Materia-Prima', '', 0.00, 0.00, 0.00, '', '', 0.00, 0.00, 0.00, 0, 0, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `subgrupos_lookup`
--

CREATE TABLE `subgrupos_lookup` (
  `id` int(11) NOT NULL,
  `nome` varchar(20) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `subgrupos_lookup`
--

INSERT INTO `subgrupos_lookup` (`id`, `nome`, `descricao`) VALUES
(1, 'Clear', 'Material Bom'),
(2, 'Classe 2', 'Material de Segunda'),
(13, 'Recup', NULL),
(34, 'Metais', 'Subgrupo de materiais metálicos.'),
(35, 'Plásticos', 'Subgrupo de materiais plásticos.'),
(36, 'Madeira', 'Subgrupo de materiais de madeira.'),
(37, 'Sensores', 'Componentes eletrônicos de detecção.'),
(38, 'Circuitos Integrados', 'Chips e circuitos eletrônicos.'),
(39, 'Conectores', 'Dispositivos de conexão.'),
(40, 'Usinagem CNC', 'Peças produzidas por máquinas CNC.'),
(41, 'Injeção', 'Peças produzidas por injeção.'),
(42, 'Caixas de Papelão', 'Embalagens de papelão.'),
(43, 'Plástico Bolha', 'Embalagens de proteção.'),
(55, 'S1', NULL),
(58, 'A', NULL),
(59, 'S2', NULL),
(63, 'Pinus', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `unidades_medida_lookup`
--

CREATE TABLE `unidades_medida_lookup` (
  `id` int(11) NOT NULL,
  `nome` varchar(10) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `unidades_medida_lookup`
--

INSERT INTO `unidades_medida_lookup` (`id`, `nome`, `descricao`) VALUES
(1, 'M3', 'Metro Cubico'),
(2, 'PC', 'Peças'),
(16, 'KG', NULL),
(29, 'M', 'Metro'),
(30, 'M2', 'Metro Quadrado'),
(31, 'L', 'Litro'),
(45, 'TON', NULL),
(56, 'UN', NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `acabamentos_lookup`
--
ALTER TABLE `acabamentos_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `apontamentos_producao`
--
ALTER TABLE `apontamentos_producao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lote_numero` (`lote_numero`),
  ADD KEY `fk_apont_op` (`ordem_producao_id`),
  ADD KEY `fk_apont_maquina` (`maquina_id`),
  ADD KEY `fk_apont_operador` (`operador_id`);

--
-- Índices de tabela `consumo_producao`
--
ALTER TABLE `consumo_producao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `apontamento_id` (`apontamento_id`),
  ADD KEY `ordem_producao_id` (`ordem_producao_id`),
  ADD KEY `produto_material_id` (`produto_material_id`),
  ADD KEY `responsavel_id` (`responsavel_id`);

--
-- Índices de tabela `empenho_materiais`
--
ALTER TABLE `empenho_materiais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_empenho_op_produto` (`produto_id`,`ordem_producao_id`),
  ADD KEY `ordem_producao_id` (`ordem_producao_id`);

--
-- Índices de tabela `familias_lookup`
--
ALTER TABLE `familias_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `fornecedores_clientes_lookup`
--
ALTER TABLE `fornecedores_clientes_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`),
  ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Índices de tabela `grupos_lookup`
--
ALTER TABLE `grupos_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `lista_materiais`
--
ALTER TABLE `lista_materiais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bom_item` (`produto_pai_id`,`produto_filho_id`),
  ADD KEY `produto_filho_id` (`produto_filho_id`);

--
-- Índices de tabela `localizacoes_lookup`
--
ALTER TABLE `localizacoes_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `maquinas`
--
ALTER TABLE `maquinas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_serie` (`numero_serie`),
  ADD UNIQUE KEY `tag_ativo` (`tag_ativo`);

--
-- Índices de tabela `materiais_insumos_entrada`
--
ALTER TABLE `materiais_insumos_entrada`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `fornecedor_id` (`fornecedor_id`),
  ADD KEY `responsavel_recebimento_id` (`responsavel_recebimento_id`);

--
-- Índices de tabela `modelos_lookup`
--
ALTER TABLE `modelos_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `operadores`
--
ALTER TABLE `operadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Índices de tabela `ordens_producao`
--
ALTER TABLE `ordens_producao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_op` (`numero_op`),
  ADD KEY `fk_op_produto` (`produto_id`),
  ADD KEY `fk_op_maquina` (`maquina_id`);

--
-- Índices de tabela `pedidos_venda_lookup`
--
ALTER TABLE `pedidos_venda_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_pedido` (`numero_pedido`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `subgrupos_lookup`
--
ALTER TABLE `subgrupos_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `unidades_medida_lookup`
--
ALTER TABLE `unidades_medida_lookup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `acabamentos_lookup`
--
ALTER TABLE `acabamentos_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT de tabela `apontamentos_producao`
--
ALTER TABLE `apontamentos_producao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

--
-- AUTO_INCREMENT de tabela `consumo_producao`
--
ALTER TABLE `consumo_producao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT de tabela `empenho_materiais`
--
ALTER TABLE `empenho_materiais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT de tabela `familias_lookup`
--
ALTER TABLE `familias_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT de tabela `fornecedores_clientes_lookup`
--
ALTER TABLE `fornecedores_clientes_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `grupos_lookup`
--
ALTER TABLE `grupos_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT de tabela `lista_materiais`
--
ALTER TABLE `lista_materiais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `localizacoes_lookup`
--
ALTER TABLE `localizacoes_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `maquinas`
--
ALTER TABLE `maquinas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `materiais_insumos_entrada`
--
ALTER TABLE `materiais_insumos_entrada`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `modelos_lookup`
--
ALTER TABLE `modelos_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de tabela `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=516;

--
-- AUTO_INCREMENT de tabela `operadores`
--
ALTER TABLE `operadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `ordens_producao`
--
ALTER TABLE `ordens_producao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de tabela `pedidos_venda_lookup`
--
ALTER TABLE `pedidos_venda_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=225;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT de tabela `subgrupos_lookup`
--
ALTER TABLE `subgrupos_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT de tabela `unidades_medida_lookup`
--
ALTER TABLE `unidades_medida_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `apontamentos_producao`
--
ALTER TABLE `apontamentos_producao`
  ADD CONSTRAINT `fk_apont_maquina` FOREIGN KEY (`maquina_id`) REFERENCES `maquinas` (`id`),
  ADD CONSTRAINT `fk_apont_op` FOREIGN KEY (`ordem_producao_id`) REFERENCES `ordens_producao` (`id`),
  ADD CONSTRAINT `fk_apont_operador` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`);

--
-- Restrições para tabelas `consumo_producao`
--
ALTER TABLE `consumo_producao`
  ADD CONSTRAINT `consumo_producao_ibfk_1` FOREIGN KEY (`apontamento_id`) REFERENCES `apontamentos_producao` (`id`),
  ADD CONSTRAINT `consumo_producao_ibfk_2` FOREIGN KEY (`ordem_producao_id`) REFERENCES `ordens_producao` (`id`),
  ADD CONSTRAINT `consumo_producao_ibfk_3` FOREIGN KEY (`produto_material_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `consumo_producao_ibfk_4` FOREIGN KEY (`responsavel_id`) REFERENCES `operadores` (`id`);

--
-- Restrições para tabelas `empenho_materiais`
--
ALTER TABLE `empenho_materiais`
  ADD CONSTRAINT `empenho_materiais_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `empenho_materiais_ibfk_2` FOREIGN KEY (`ordem_producao_id`) REFERENCES `ordens_producao` (`id`);

--
-- Restrições para tabelas `lista_materiais`
--
ALTER TABLE `lista_materiais`
  ADD CONSTRAINT `lista_materiais_ibfk_1` FOREIGN KEY (`produto_pai_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `lista_materiais_ibfk_2` FOREIGN KEY (`produto_filho_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `materiais_insumos_entrada`
--
ALTER TABLE `materiais_insumos_entrada`
  ADD CONSTRAINT `materiais_insumos_entrada_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `materiais_insumos_entrada_ibfk_2` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores_clientes_lookup` (`id`),
  ADD CONSTRAINT `materiais_insumos_entrada_ibfk_3` FOREIGN KEY (`responsavel_recebimento_id`) REFERENCES `operadores` (`id`);

--
-- Restrições para tabelas `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  ADD CONSTRAINT `movimentacoes_estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `ordens_producao`
--
ALTER TABLE `ordens_producao`
  ADD CONSTRAINT `fk_op_maquina` FOREIGN KEY (`maquina_id`) REFERENCES `maquinas` (`id`),
  ADD CONSTRAINT `fk_op_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

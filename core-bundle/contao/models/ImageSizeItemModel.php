<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Contao\Model\Collection;

/**
 * Reads and writes image size items
 *
 * @property integer      $id
 * @property integer      $pid
 * @property integer      $sorting
 * @property integer      $tstamp
 * @property string       $media
 * @property string       $sizes
 * @property string       $densities
 * @property integer|null $width
 * @property integer|null $height
 * @property string       $resizeMode
 * @property integer|null $zoom
 * @property boolean      $invisible
 *
 * @method static ImageSizeItemModel|null findById($id, array $opt=array())
 * @method static ImageSizeItemModel|null findByPk($id, array $opt=array())
 * @method static ImageSizeItemModel|null findByIdOrAlias($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneBy($col, $val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByPid($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneBySorting($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByTstamp($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByMedia($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneBySizes($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByDensities($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByWidth($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByHeight($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByResizeMode($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByZoom($val, array $opt=array())
 * @method static ImageSizeItemModel|null findOneByInvisible($val, array $opt=array())
 *
 * @method static Collection<ImageSizeItemModel>|null findByPid($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findBySorting($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findByTstamp($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findByMedia($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findBySizes($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findByDensities($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findByWidth($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findByHeight($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findByResizeMode($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findByZoom($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findByInvisible($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findMultipleByIds($val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findBy($col, $val, array $opt=array())
 * @method static Collection<ImageSizeItemModel>|null findAll(array $opt=array())
 *
 * @method static integer countById($id, array $opt=array())
 * @method static integer countByPid($val, array $opt=array())
 * @method static integer countBySorting($val, array $opt=array())
 * @method static integer countByTstamp($val, array $opt=array())
 * @method static integer countByMedia($val, array $opt=array())
 * @method static integer countBySizes($val, array $opt=array())
 * @method static integer countByDensities($val, array $opt=array())
 * @method static integer countByWidth($val, array $opt=array())
 * @method static integer countByHeight($val, array $opt=array())
 * @method static integer countByResizeMode($val, array $opt=array())
 * @method static integer countByZoom($val, array $opt=array())
 * @method static integer countByInvisible($val, array $opt=array())
 */
class ImageSizeItemModel extends Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_image_size_item';

	/**
	 * Find visible image size items by their parent ID
	 *
	 * @param integer $intPid     Parent ID
	 * @param array   $arrOptions An optional options array
	 *
	 * @return Collection<ImageSizeItemModel>|null A collection of models or null if there are no items
	 */
	public static function findVisibleByPid($intPid, array $arrOptions=array())
	{
		$t = static::$strTable;

		return static::findBy(array("$t.pid=? AND $t.invisible=0 AND $t.tstamp>0"), (int) $intPid, $arrOptions);
	}
}

<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Generator\Builder\Om;

use Propel\Generator\Exception\BuildException;
use Propel\Generator\Model\Inheritance;

/**
 * Generates the empty PHP5 stub query class for use with single table
 * inheritance.
 *
 * This class produces the empty stub class that can be customized with
 * application business logic, custom behavior, etc.
 *
 *
 * @author François Zaninotto
 */
class QueryInheritanceBuilder extends AbstractOMBuilder
{

    /**
     * The current child "object" we are operating on.
     */
    protected $child;

    /**
     * Returns the name of the current class being built.
     *
     * @return string
     */
    public function getUnprefixedClassName()
    {
        return $this->getNewStubQueryInheritanceBuilder( $this->getChild() )->getUnprefixedClassName();
    }

    /**
     * Gets the package for the [base] object classes.
     *
     * @return string
     */
    public function getPackage()
    {
        return ($this->getChild()->getPackage() ? $this->getChild()->getPackage() : parent::getPackage()) . '.Base';
    }

    /**
     * Gets the namespace for the [base] object classes.
     *
     * @return string
     */
    public function getNamespace()
    {
        if ( $namespace = parent::getNamespace() )
        {
            return $namespace . '\\Base';
        }

        return 'Base';
    }

    /**
     * Sets the child object that we're operating on currently.
     *
     * @param Inheritance $child
     */
    public function setChild( Inheritance $child )
    {
        $this->child = $child;
    }

    /**
     * Returns the child object we're operating on currently.
     *
     * @return Inheritance
     * @throws BuildException
     */
    public function getChild()
    {
        if ( !$this->child )
        {
            throw new BuildException( "The PHP5MultiExtendObjectBuilder needs to be told which child class to build (via setChild() method) before it can build the stub class." );
        }

        return $this->child;
    }

    /**
     * Returns classpath to parent class.
     *
     * @return string
     */
    protected function getParentClassName()
    {
        if ( is_null( $this->getChild()->getAncestor() ) )
        {
            return $this->getNewStubQueryBuilder( $this->getTable() )->getUnqualifiedClassName();
        }

        $ancestorClassName = ClassTools::classname( $this->getChild()->getAncestor() );
        if ( $this->getDatabase()->hasTableByPhpName( $ancestorClassName ) )
        {
            return $this->getNewStubQueryBuilder( $this->getDatabase()->getTableByPhpName( $ancestorClassName ) )->getUnqualifiedClassName();
        }

        // find the inheritance for the parent class
        foreach ( $this->getTable()->getChildrenColumn()->getChildren() as $child )
        {
            if ( $child->getClassName() == $ancestorClassName )
            {
                return $this->getNewStubQueryInheritanceBuilder( $child )->getUnqualifiedClassName();
            }
        }
    }

    /**
     * Adds class phpdoc comment and opening of class.
     *
     * @param string &$script
     */
    protected function addClassOpen( &$script )
    {
        $table = $this->getTable();
        $tableName = $table->getName();

        $tableDesc = $table->getDescription();
        $tableDescComment = ($tableDesc == '') ? '' : " *\n * {$tableDesc}\n";

        $baseBuilder = $this->getStubQueryBuilder();
        $this->declareClassFromBuilder( $baseBuilder );
        $baseClassName = $this->getParentClassName();

        $script .= "
/**
 * Skeleton subclass for representing a query for one of the subclasses of the '$tableName' table.
$tableDescComment *";
        if ( $this->getBuildProperty( 'generator.objectModel.addTimeStamp' ) )
        {
            $now = strftime( '%c' );
            $script .= "
 * This class was autogenerated by Propel " . $this->getBuildProperty( 'general.version' ) . " on:
 *
 * $now
 *";
        }
        $script .= "
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class " . $this->getUnqualifiedClassName() . " extends " . $baseClassName . "
{
";
    }

    /**
     * Specifies the methods that are added as part of the stub object class.
     *
     * By default there are no methods for the empty stub classes; override this
     * method if you want to change that behavior.
     *
     * @param string $script
     * @see ObjectBuilder::addClassBody()
     */
    protected function addClassBody( &$script )
    {
        $this->declareClassFromBuilder( $this->getTableMapBuilder() );
        $this->declareClasses(
                '\Propel\Runtime\Connection\ConnectionInterface', '\Propel\Runtime\ActiveQuery\Criteria'
        );
        $this->addFactory( $script );
        $this->addPreSelect( $script );
        $this->addPreUpdate( $script );
        $this->addPreDelete( $script );
        $this->addDoDeleteAll( $script );
    }

    /**
     * Adds the factory for this object.
     *
     * @param string &$script
     */
    protected function addFactory( &$script )
    {
        $builder = $this->getNewStubQueryInheritanceBuilder( $this->getChild() );
        $this->declareClassFromBuilder( $builder, 'Child' );
        $classname = $builder->getClassName();
        $script .= "
    /**
     * Returns a new " . $classname . " object.
     *
     * @param     string \$modelAlias The alias of a model in the query
     * @param     Criteria \$criteria Optional Criteria to build the query from
     *
     * @return " . $classname . "
     */
    public static function create(\$modelAlias = null, Criteria \$criteria = null)
    {
        if (\$criteria instanceof " . $classname . ") {
            return \$criteria;
        }
        \$query = new " . $classname . "();
        if (null !== \$modelAlias) {
            \$query->setModelAlias(\$modelAlias);
        }
        if (\$criteria instanceof Criteria) {
            \$query->mergeWith(\$criteria);
        }

        return \$query;
    }
";
    }

    protected function addPreSelect( &$script )
    {
        $child = $this->getChild();

        $script .= "
    /**
     * Filters the query to target only " . $child->getClassName() . " objects.
     */
    public function preSelect(ConnectionInterface \$con)
    {
        " . $this->getClassKeyCondition() . "
    }
";
    }

    protected function addPreUpdate( &$script )
    {
        $child = $this->getChild();

        $script .= "
    /**
     * Filters the query to target only " . $child->getClassName() . " objects.
     */
    public function preUpdate(&\$values, ConnectionInterface \$con, \$forceIndividualSaves = false)
    {
        " . $this->getClassKeyCondition() . "
    }
";
    }

    protected function addPreDelete( &$script )
    {
        $child = $this->getChild();

        $script .= "
    /**
     * Filters the query to target only " . $child->getClassName() . " objects.
     */
    public function preDelete(ConnectionInterface \$con)
    {
        " . $this->getClassKeyCondition() . "
    }
";
    }

    protected function getClassKeyCondition()
    {
        $child = $this->getChild();
        $col = $child->getColumn();

        return "\$this->addUsingAlias(" . $col->getFQConstantName() . ", " . $this->getTableMapClassName() . "::CLASSKEY_" . strtoupper( $child->getKey() ) . ");";
    }

    protected function addDoDeleteAll( &$script )
    {
        $child = $this->getChild();

        $script .= "
    /**
     * Issue a DELETE query based on the current ModelCriteria deleting all rows in the table
     * Having the " . $child->getClassName() . " class.
     * This method is called by ModelCriteria::deleteAll() inside a transaction
     *
     * @param ConnectionInterface \$con a connection object
     *
     * @return integer the number of deleted rows
     */
    public function doDeleteAll(ConnectionInterface \$con = null)
    {
        // condition on class key is already added in preDelete()
        return parent::delete(\$con);
    }
";
    }

    /**
     * Closes class.
     *
     * @param string &$script
     */
    protected function addClassClose( &$script )
    {
        $script .= "
} // " . $this->getUnqualifiedClassName() . "
";
    }

}

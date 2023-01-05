import React, {
    forwardRef,
    Fragment,
    useImperativeHandle,
    useState,
} from "react";
import {useDidMountEffect} from "../../custom-hooks";
import Tree from "rc-tree";
import Dropdown from "react-bootstrap/Dropdown";
import "rc-tree/assets/index.less";
import styles from "./multi-select-tree-checkbox-dropdown.module.css";
import "./multi-select-tree-checkbox-dropdown.scss";

function MultiSelectTreeCheckboxDropdown(props, ref) {
    const {treeData, onCheck, width, renderableDataUpdate} = props;
    const [checkedKeys, setCheckedKeys] = useState([]);
    const [filteredTreeData, setFilteredTreeData] = useState([]);
    const [renderableData, setRenderableData] = useState({
        dataUpdateFromParent: false,
        data: [],
    });
    const [filterText, setFilterText] = useState("");
    const [selectedItemsText, setSelectedItemsText] = useState(null);
    const [renderableDataNodeCount, setRenderableDataNodeCount] = useState(0);
    const [isOpen, setIsOpen] = useState(false);

    const initial = {
        titles: [],
        total: 0
    }

    const parseSelected = (o, results = initial) => {
        results.total++;
        if(checkedKeys.includes(o.key)){
            results.titles.push(o.title);
        }

        for (const child of o.children) {
            parseSelected(child);
        }

        return results;
    }

    const getTitle = (o) => {
        const results = parseSelected(o);

        if (checkedKeys.length === 0) {
            return 'Select department(s)';
        }

        if (checkedKeys.length === 1 && treeData[0]?.key === checkedKeys[0]) {
            return 'Current department is selected';
        }

        if (results.total === checkedKeys.length) {
            return 'All departments are selected';
        }

        return results.titles.join(', ');
    }

    /* when tree data updates */
    useDidMountEffect(() => {
        if (treeData?.length) {
            setCheckedKeys([treeData[0].key]);
        } else {
            setCheckedKeys([]);
        }
        setRenderableData({
            dataUpdateFromParent: true,
            data: treeData,
        });
    }, [treeData]);

    useDidMountEffect(() => {
        renderableDataUpdate({
            dataUpdateFromParent: renderableData["dataUpdateFromParent"],
        });

        let data = getAllKeysAndTitleFromNodes(renderableData["data"]);

        setRenderableDataNodeCount(data["keys"].length);
    }, [renderableData]);

    /* on checkedKeys updates */
    useDidMountEffect(() => {
        onCheck(checkedKeys);
    }, [checkedKeys]);

    useDidMountEffect(() => {
        let title = '';
        if (treeData.length) {
            title = getTitle(treeData[0]);
        }
        setSelectedItemsText(title);
    }, [checkedKeys, treeData])

    // The component instance will be extended
    // with whatever you return from the callback passed
    // as the second argument
    useImperativeHandle(ref, () => ({
        selectAll,
        unSelectAll,
        selectItems,
        setSelectedItemsText
    }));

    /* Select Items */
    const selectItems = (items) => {
        setCheckedKeys(items)
    }

    /* Handling department checked update */
    const handleDepartmentSelect = (selectedDepartments, event) => {
        const defaultKey = treeData[0]?.key;
        const keys = selectedDepartments.checked;

        setCheckedKeys(() => keys.length ? keys : [defaultKey]);
    };

    /* Handles filter input change */
    const onFilterChange = (event) => {
        let inputText = event.target.value;

        setFilterText(inputText);

        if (inputText.length == 0) {
            /* Resetting filterTreeData */
            setRenderableData({
                dataUpdateFromParent: false,
                data: treeData,
            });

            return;
        }

        /* performing filter */
        filterTree();
    };

    /* Selects the all node of filtered data */
    const selectAll = () => {
        /* Un-selecting previous selected before selecting */
        // unSelectAll()

        /* Gettting the nodes form filtered data */
        let data = getAllKeysAndTitleFromNodes(renderableData["data"]);
        /*  */
        setCheckedKeys(data["keys"]);
    };

    /* Un-select all */
    const unSelectAll = () => {
        const defaultKey = treeData[0]?.key;
        setCheckedKeys([defaultKey]);
    };

    /* Trigger when select all option checked or unchecked */
    const handleSelectAllChange = (event) => {
        let isChecked = event.target.checked;

        isChecked ? selectAll() : unSelectAll();
    };

    const filterTree = () => {
        const nodesFiltered = treeData.reduce(filterNodes, []);

        setRenderableData({
            dataUpdateFromParent: false,
            data: nodesFiltered,
        });
    };

    const filterNodes = (filtered, node) => {
        let children;
        if (!!node.children) {
            children = node.children.reduce(filterNodes, []);
        }
        if (
            node.title
                .toLocaleLowerCase()
                .indexOf(filterText.toLocaleLowerCase()) > -1 ||
            (children && children.length)
        ) {
            //   var expanded = [];
            //   expanded = [node.key, ...this.getAllValuesFromNodes(children)];
            //   this.setState({ expanded });
            if (!!node.children) {
                filtered.push({...node, children});
            } else {
                filtered.push(node);
            }
        }
        return filtered;
    };

    const getAllKeysAndTitleFromNodes = (nodes) => {
        const data = [];
        (data["keys"] = []), (data["titles"] = []);

        if (!!nodes)
            for (let n of nodes) {
                data["keys"].push(n.key);
                data["titles"].push(n.title);
                if (n.children) {
                    let childData = getAllKeysAndTitleFromNodes(
                        n.children,
                        false
                    );
                    data["keys"].push(...childData["keys"]);
                    data["titles"].push(...childData["titles"]);
                }
            }
        return data;
    };

    /* Clears the search input */
    const clearSearchInput = () => {
        setFilterText("");
        setFilteredTreeData(treeData);
        setRenderableData({
            dataUpdateFromParent: false,
            data: treeData,
        });
    };

    /* Conditionally renders the dropdown heading clear icon */
    const renderDropdownHeadingClearIcon = () => {
        return (
            checkedKeys.length > 0 && (
                <button
                    type="button"
                    onClick={() => {
                        unSelectAll();
                    }}
                    className="clear-selected-button"
                    aria-label="Clear Selected"
                >
                    <svg
                        width="24"
                        height="24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        className="dropdown-search-clear-icon gray"
                    >
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            )
        );
    };

    /* Conditional rendering of search input clear icon */
    const renderSearchInputClearIcon = () => {
        return (
            filterText && (
                <button
                    type="button"
                    onClick={() => {
                        clearSearchInput();
                    }}
                    className={`search-clear-button ${styles.searchClearButton}`}
                    aria-label="Clear Search"
                >
                    <svg
                        width={24}
                        height={24}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={2}
                        className="dropdown-search-clear-icon gray"
                    >
                        <line x1={18} y1={6} x2={6} y2={18}/>
                        <line x1={6} y1={6} x2={18} y2={18}/>
                    </svg>
                </button>
            )
        );
    };

    /* Handling the select all option input checking when checkedKeys updates */
    const renderSelectAllOption = () => {
        let isChecked =
            renderableDataNodeCount > 0 &&
            checkedKeys.length >= renderableDataNodeCount;

        return (
            <div>
                <input
                    type="checkbox"
                    onChange={handleSelectAllChange}
                    checked={isChecked}
                    className={styles.selectAllCheckboxInput}
                />
                <span>
                    Select All {filterText.length > 0 ? "( Filtered )" : ""}
                </span>
            </div>
        );
    };

    const renderToggleDropdownIcon = () => {
        let path = isOpen ? (
            <path d="M18 15 12 9 6 15"></path>
        ) : (
            <path d="M6 9L12 15 18 9"></path>
        );

        return (
            <svg
                width="24"
                height="24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                className="dropdown-heading-dropdown-arrow gray"
            >
                {path}
            </svg>
        );
    };

    /* Handling dropdown toggel event */
    const handleDropdownToggleEvent = (isOpen) => {
        setIsOpen(isOpen);
    };

    return (
        <Fragment>
            <Dropdown
                bsPrefix="multi-select-tree-dropdown dropdown"
                onToggle={handleDropdownToggleEvent}
            >
                <Dropdown.Toggle
                    bsPrefix="cursor-pointer dropdown-toggle"
                    as="a"
                    variant="success"
                    id="dropdown-basic"
                >
                    <div
                        className={`${styles.dropdownHeadingContainer}`}
                        style={{minWidth: `${width}px`}}
                    >
                        <div className={styles.dropdownHeading}>
                            <div className={styles.dropdownHeadingValue} style={{maxWidth: '12rem'}}>
                                <span className="gray">
                                    {selectedItemsText}
                                </span>
                            </div>
                            {renderDropdownHeadingClearIcon()}

                            {renderToggleDropdownIcon()}
                        </div>
                    </div>
                </Dropdown.Toggle>

                <Dropdown.Menu style={{width: `${width}px`}}>
                    <div className="dropdown-menu-content">
                        {/* Search list */}
                        <div className={`search ${styles.search}`}>
                            <input
                                placeholder="Search department(s)"
                                value={filterText}
                                className={styles.searchInput}
                                onChange={onFilterChange}
                                type="text"
                                aria-describedby="Search Department"
                                tabIndex={0}
                            />
                            {renderSearchInputClearIcon()}
                        </div>

                        <div className={styles.dropdownWp}>
                            {renderSelectAllOption()}
                            <Tree
                                checkable
                                checkStrictly
                                className="myCls"
                                defaultExpandAll
                                treeData={renderableData["data"]}
                                onCheck={handleDepartmentSelect}
                                height={150}
                                checkedKeys={checkedKeys}
                                showIcon={false}
                            />
                        </div>
                    </div>
                </Dropdown.Menu>
            </Dropdown>
        </Fragment>
    );
}

export default forwardRef(MultiSelectTreeCheckboxDropdown);
